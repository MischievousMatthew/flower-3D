<template>
  <HrPage :title="isEdit ? `Edit ${loadedNumber || 'draft'}` : 'New funding request'" :subtitle="isEdit ? 'Change the draft, then send it to Finance when it\'s ready.' : 'Pick the products you need to restock, say when and why, and how Finance should pay.'">
    <template #actions>
      <button type="button" class="sp-btn sp-btn-ghost" @click="goBack"><span v-html="ICONS.chevronLeft"></span>{{ isEdit ? "Back to request" : "All requests" }}</button>
    </template>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="init">Try again</button>
    </div>

    <div v-if="!ready && !loadError" class="fr-sk">
      <div class="sp-card"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-block"></div></div>
      <div class="sp-card"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-block"></div></div>
    </div>

    <form v-else-if="ready" class="fr" novalidate @submit.prevent="save(true)">
      <div class="fr-main">
        <p v-if="!canSave" class="sp-banner warn"><span v-html="ICONS.lock"></span>You can view Inventory Funding but not {{ isEdit ? "edit" : "create" }} requests.</p>

        <!-- Products -->
        <section class="sp-card" aria-labelledby="fr-products">
          <div class="sp-card-head">
            <div><h2 id="fr-products">Products</h2><p>Tick everything you need. Each one gets its own quantity and cost.</p></div>
          </div>

          <div class="fr-pick-bar">
            <label class="fr-search">
              <span v-html="ICONS.search" aria-hidden="true"></span>
              <input v-model.trim="search" type="search" class="sp-input" placeholder="Search by name, SKU or category" aria-label="Search products" />
            </label>
            <div class="fr-filters" role="group" aria-label="Show">
              <button type="button" class="fr-filter" :class="{ on: filter === 'all' }" @click="filter = 'all'">All <small>{{ products.length }}</small></button>
              <button type="button" class="fr-filter" :class="{ on: filter === 'low' }" @click="filter = 'low'">Low stock <small>{{ lowCount }}</small></button>
              <button type="button" class="fr-filter" :class="{ on: filter === 'picked' }" @click="filter = 'picked'">Picked <small>{{ form.items.length }}</small></button>
            </div>
          </div>

          <p v-if="!products.length" class="fr-empty">No products in your inventory yet. Add products first, then come back to request funds for them.</p>
          <ul v-else class="fr-picker" role="listbox" aria-multiselectable="true" aria-label="Products">
            <li v-for="p in visibleProducts" :key="p.id">
              <button type="button" role="option" class="fr-option" :class="{ on: isPicked(p.id) }" :aria-selected="isPicked(p.id)" @click="toggle(p)">
                <span class="fr-tick" aria-hidden="true"><span v-if="isPicked(p.id)" v-html="ICONS.check"></span></span>
                <span class="fr-option-name">
                  <b>{{ p.product_name }}</b>
                  <small>{{ [p.sku, p.category || p.flower_type].filter(Boolean).join(" · ") || "No SKU" }}</small>
                </span>
                <span class="sp-chip fr-stock" :class="stockTone(p)">{{ p.quantity_in_stock ?? 0 }} {{ uomOf(p).toLowerCase() }}</span>
                <span class="fr-option-cost">{{ money(p.purchase_price) }}</span>
              </button>
            </li>
            <li v-if="!visibleProducts.length" class="fr-empty">{{ filter === "low" ? "Nothing is running low." : filter === "picked" ? "Nothing picked yet." : "No product matches that search." }}</li>
          </ul>

          <!-- What's been picked -->
          <div class="fr-lines">
            <div class="fr-lines-head">
              <span class="sp-label">Requesting</span>
              <button v-if="form.items.length" type="button" class="fr-link" @click="form.items = []">Clear all</button>
            </div>
            <p v-if="!form.items.length" class="fr-empty" :class="{ bad: err.items }">{{ err.items || "Nothing yet. Tick products above to add them." }}</p>
            <div v-for="(line, i) in form.items" :key="line.product_id" class="fr-line">
              <div class="fr-line-name">
                <b>{{ line.product_name }}</b>
                <small>In stock: {{ line.stock }} {{ line.uom.toLowerCase() }}<template v-if="line.max"> · max {{ line.max }}</template></small>
              </div>
              <label class="sp-field fr-qty">
                <span class="sp-label">Quantity</span>
                <span class="fr-affix">
                  <input v-model.number="line.quantity" type="number" min="1" step="1" inputmode="numeric" class="sp-input" :class="{ invalid: err[`items.${i}.quantity`] }" :aria-label="`Quantity of ${line.product_name}`" />
                  <em>{{ line.uom.toLowerCase() }}</em>
                </span>
              </label>
              <label class="sp-field fr-cost">
                <span class="sp-label">Unit cost</span>
                <span class="fr-affix pre">
                  <em>₱</em>
                  <input v-model.number="line.unit_cost" type="number" min="0" step="0.01" inputmode="decimal" class="sp-input" :class="{ invalid: err[`items.${i}.unit_cost`] }" :aria-label="`Unit cost of ${line.product_name}`" />
                </span>
              </label>
              <div class="fr-line-total">
                <span class="sp-label">Amount</span>
                <b>{{ money(lineTotal(line)) }}</b>
              </div>
              <button type="button" class="sp-icon-btn fr-remove" :aria-label="`Remove ${line.product_name}`" @click="form.items.splice(i, 1)"><span v-html="ICONS.trash"></span></button>
              <p v-if="err[`items.${i}.quantity`] || err[`items.${i}.unit_cost`] || err[`items.${i}.product_id`]" class="fr-note bad">
                <span v-html="ICONS.alert"></span>{{ err[`items.${i}.quantity`] || err[`items.${i}.unit_cost`] || err[`items.${i}.product_id`] }}
              </p>
              <p v-else-if="line.max && line.stock + Number(line.quantity || 0) > line.max" class="fr-note">
                <span v-html="ICONS.alert"></span>This brings stock to {{ line.stock + Number(line.quantity || 0) }}, above the max of {{ line.max }}.
              </p>
            </div>
          </div>
        </section>

        <!-- When and why -->
        <section class="sp-card" aria-labelledby="fr-why">
          <div class="sp-card-head"><div><h2 id="fr-why">When &amp; why</h2><p>Helps Finance decide what to fund first</p></div></div>
          <div class="sp-grid-2">
            <label class="sp-field">
              <span class="sp-label">Needed by</span>
              <input v-model="form.required_delivery_date" type="date" class="sp-input" :class="{ invalid: err.required_delivery_date }" :min="today" />
              <small v-if="err.required_delivery_date" class="sp-error">{{ err.required_delivery_date }}</small>
            </label>
            <label class="sp-field">
              <span class="sp-label">Reason</span>
              <select v-model="form.reason_for_shortage" class="sp-select" :class="{ invalid: err.reason_for_shortage }">
                <option value="">Choose a reason</option>
                <option v-for="r in REASONS" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
              <small v-if="err.reason_for_shortage" class="sp-error">{{ err.reason_for_shortage }}</small>
            </label>
            <fieldset class="sp-field sp-span-all fr-fieldset">
              <legend class="sp-label">Urgency</legend>
              <div class="fr-seg">
                <label v-for="u in URGENCY" :key="u.value" class="fr-seg-opt" :class="[u.tone, { on: form.urgency_level === u.value }]">
                  <input v-model="form.urgency_level" type="radio" name="fr-urgency" :value="u.value" />
                  <b>{{ u.value }}</b><small>{{ u.hint }}</small>
                </label>
              </div>
            </fieldset>
            <label class="sp-field sp-span-all">
              <span class="sp-label">Why it's needed</span>
              <input v-model="form.business_justification" type="text" class="sp-input" :class="{ invalid: err.business_justification }" maxlength="150" placeholder="e.g. Valentine's orders need 500 more red roses than we have" />
              <small v-if="err.business_justification" class="sp-error">{{ err.business_justification }}</small>
              <small v-else class="sp-hint fr-count">{{ form.business_justification.length }}/150</small>
            </label>
            <label class="sp-field sp-span-all">
              <span class="sp-label">Notes for Finance <small>(optional)</small></span>
              <textarea v-model="form.additional_notes" class="sp-textarea" rows="2" maxlength="300" placeholder="Anything else they should know"></textarea>
            </label>
          </div>
        </section>

        <!-- Payment -->
        <section class="sp-card" aria-labelledby="fr-pay">
          <div class="sp-card-head"><div><h2 id="fr-pay">Payment</h2><p>How Finance should release the money</p></div></div>
          <div class="fr-methods" role="radiogroup" aria-label="Payment method">
            <label v-for="m in METHODS" :key="m.value" class="fr-method" :class="{ on: form.payment_method === m.value, invalid: err.payment_method }">
              <input v-model="form.payment_method" type="radio" name="fr-method" :value="m.value" @change="form.payment_channel = ''" />
              <span class="fx-icon" v-html="ICONS[m.icon]"></span>
              <span><b>{{ m.label }}</b><small>{{ m.hint }}</small></span>
            </label>
          </div>
          <small v-if="err.payment_method" class="sp-error">{{ err.payment_method }}</small>

          <fieldset v-if="form.payment_method" class="fr-fieldset fr-channels-wrap">
            <legend class="sp-label">{{ form.payment_method === "bank" ? "Bank" : "E-wallet" }}</legend>
            <div class="fr-channels">
              <label v-for="c in CHANNELS[form.payment_method]" :key="c" class="fr-channel" :class="{ on: form.payment_channel === c }">
                <input v-model="form.payment_channel" type="radio" name="fr-channel" :value="c" />{{ c }}
              </label>
            </div>
            <small v-if="err.payment_channel" class="sp-error">{{ err.payment_channel }}</small>
          </fieldset>

          <label class="sp-field fr-delivery">
            <span class="sp-label">Delivery fee <small>(optional)</small></span>
            <span class="fr-affix pre">
              <em>₱</em>
              <input v-model.number="form.logistics_cost" type="number" min="0" step="0.01" inputmode="decimal" class="sp-input" :class="{ invalid: err.logistics_cost }" placeholder="0.00" />
            </span>
            <small v-if="err.logistics_cost" class="sp-error">{{ err.logistics_cost }}</small>
          </label>
        </section>
      </div>

      <!-- Summary and actions -->
      <aside class="fr-side">
        <div class="sp-card fr-sticky">
          <label class="sp-field">
            <span class="sp-label">Finance approver</span>
            <select v-model="form.approver_id" class="sp-select" :class="{ invalid: err.approver_id }">
              <option value="">Who reviews it?</option>
              <option v-for="a in approvers" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
            <small v-if="err.approver_id" class="sp-error">{{ err.approver_id }}</small>
            <small v-else-if="!approvers.length" class="sp-hint">No one can approve funding yet. Give someone Finance's approve permission first.</small>
          </label>

          <dl class="fr-sum">
            <div><dt>Products</dt><dd>{{ form.items.length }}</dd></div>
            <div><dt>Units</dt><dd>{{ totalUnits.toLocaleString("en-PH") }}</dd></div>
            <div><dt>Products cost</dt><dd>{{ money(subtotal) }}</dd></div>
            <div v-if="Number(form.logistics_cost) > 0"><dt>Delivery fee</dt><dd>{{ money(form.logistics_cost) }}</dd></div>
            <div class="total"><dt>Total requested</dt><dd>{{ money(total) }}</dd></div>
          </dl>
          <p v-if="form.payment_channel" class="fr-paynote"><span v-html="ICONS[form.payment_method === 'bank' ? 'card' : 'wallet']"></span>Paid through {{ form.payment_channel }}</p>

          <p v-if="formError" class="sp-error" role="alert">{{ formError }}</p>
          <button type="submit" class="sp-btn sp-btn-primary fr-btn" :disabled="!!busy || !canSave"><span v-html="ICONS.send"></span>{{ busy === "send" ? "Sending…" : "Send to Finance" }}</button>
          <button type="button" class="sp-btn sp-btn-ghost fr-btn" :disabled="!!busy || !canSave" @click="save(false)">{{ busy === "draft" ? "Saving…" : "Save as draft" }}</button>
          <small class="sp-hint">A draft stays with Procurement. Sending it asks the approver to review it.</small>
        </div>
      </aside>
    </form>
  </HrPage>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../../plugins/axios";
import HrPage from "../hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../vendor/shop/shopIcons";
import { apiMessage } from "../vendor/shop/useVendorCatalog";
import { money } from "../vendor/finance/financeUi";
import { useAssignment } from "../../composables/useAssignment";
import { toast } from "@/utils/snackbar";
import "../vendor/finance/finance.css";

const props = defineProps({
  // The draft being edited; empty for a new request
  id: { type: [String, Number], default: null },
});

const REASONS = [
  { value: "New Order", label: "Customer orders to fill" },
  { value: "Seasonal Demand", label: "Season or holiday demand" },
  { value: "Spoilage", label: "Stock spoiled or damaged" },
  { value: "Forecast Error", label: "Sold faster than expected" },
  { value: "Supplier Delay", label: "Supplier delivery was late" },
];
const URGENCY = [
  { value: "Normal", hint: "Within the usual time", tone: "slate" },
  { value: "Urgent", hint: "In the next few days", tone: "amber" },
  { value: "Critical", hint: "Orders are at risk now", tone: "red" },
];
const METHODS = [
  { value: "bank", label: "Bank transfer", hint: "Paid from a bank account", icon: "card" },
  { value: "ewallet", label: "E-wallet", hint: "GCash, Maya and others", icon: "wallet" },
];
// Matches FundingRequestController::PAYMENT_CHANNELS
const CHANNELS = {
  bank: ["BDO", "BPI", "Metrobank", "Landbank", "PNB", "Security Bank", "UnionBank", "RCBC", "China Bank", "EastWest Bank", "Other bank"],
  ewallet: ["GCash", "Maya", "GrabPay", "ShopeePay", "Coins.ph", "Other e-wallet"],
};
const BASE = "/procurement/inventory/funding-requests";
const LIST = "/erp/procurement/inventory/funding-request";

const router = useRouter();
const route = useRoute();
const { can } = useAssignment();

const editId = computed(() => props.id ?? route.params.id ?? window.history.state?.requestId ?? null);
const isEdit = computed(() => !!editId.value);
const canSave = computed(() => can("inventory_funding", isEdit.value ? "edit" : "create"));

const ready = ref(false);
const loadError = ref("");
const loadedNumber = ref("");
const products = ref([]);
const approvers = ref([]);
const search = ref("");
const filter = ref("all");
const busy = ref("");
const formError = ref("");
const err = reactive({});

const pad = (n) => String(n).padStart(2, "0");
const now = new Date();
const today = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;

const form = reactive({
  approver_id: "",
  items: [],
  required_delivery_date: "",
  urgency_level: "Normal",
  reason_for_shortage: "",
  business_justification: "",
  additional_notes: "",
  payment_method: "bank",
  payment_channel: "",
  logistics_cost: null,
});

const uomOf = (p) => ({ per_piece: "Stems", per_bunch: "Bunches", per_box: "Boxes" })[p?.selling_type] || "Stems";
const isLow = (p) => Number(p.quantity_in_stock ?? 0) <= Number(p.min_stock_level ?? 0);
const stockTone = (p) => (Number(p.quantity_in_stock ?? 0) <= 0 ? "red" : isLow(p) ? "amber" : "green");
const isPicked = (id) => form.items.some((l) => l.product_id === id);
const lowCount = computed(() => products.value.filter(isLow).length);

const visibleProducts = computed(() => {
  const q = search.value.toLowerCase();
  return products.value.filter((p) => {
    if (filter.value === "low" && !isLow(p)) return false;
    if (filter.value === "picked" && !isPicked(p.id)) return false;
    return !q || [p.product_name, p.sku, p.category, p.flower_type].some((v) => String(v ?? "").toLowerCase().includes(q));
  });
});

const lineTotal = (l) => (Number(l.quantity) || 0) * (Number(l.unit_cost) || 0);
const subtotal = computed(() => form.items.reduce((s, l) => s + lineTotal(l), 0));
const total = computed(() => subtotal.value + (Number(form.logistics_cost) || 0));
const totalUnits = computed(() => form.items.reduce((s, l) => s + (Number(l.quantity) || 0), 0));

function lineFor(p, extra = {}) {
  const stock = Number(p.quantity_in_stock ?? 0);
  const max = Number(p.max_stock_level ?? 0) || null;
  // Enough to fill up to the max stock level, or the minimum when no max is set
  const suggested = max && max > stock ? max - stock : Math.max(Number(p.min_stock_level ?? 0) - stock, 1);
  return { product_id: p.id, product_name: p.product_name, uom: uomOf(p), stock, max, quantity: suggested, unit_cost: Number(p.purchase_price ?? 0), ...extra };
}

function toggle(p) {
  const i = form.items.findIndex((l) => l.product_id === p.id);
  if (i >= 0) form.items.splice(i, 1);
  else form.items.push(lineFor(p));
  delete err.items;
}

function clearErrors() {
  Object.keys(err).forEach((k) => delete err[k]);
  formError.value = "";
}

function validate(send) {
  clearErrors();
  form.items.forEach((l, i) => {
    if (!(Number.isInteger(Number(l.quantity)) && Number(l.quantity) >= 1)) err[`items.${i}.quantity`] = "Enter a whole number of 1 or more.";
    if (l.unit_cost === "" || l.unit_cost === null || Number(l.unit_cost) < 0) err[`items.${i}.unit_cost`] = "Enter the unit cost.";
  });
  if (form.logistics_cost !== null && form.logistics_cost !== "" && Number(form.logistics_cost) < 0) err.logistics_cost = "The fee can't be negative.";
  if (send) {
    if (!form.items.length) err.items = "Pick at least one product.";
    if (!form.approver_id) err.approver_id = "Choose who reviews it.";
    if (!form.required_delivery_date) err.required_delivery_date = "When do you need it?";
    if (!form.reason_for_shortage) err.reason_for_shortage = "Choose a reason.";
    if (!form.business_justification.trim()) err.business_justification = "Say in a few words why it's needed.";
    if (!form.payment_method) err.payment_method = "Choose bank transfer or e-wallet.";
    else if (!form.payment_channel) err.payment_channel = `Choose the ${form.payment_method === "bank" ? "bank" : "e-wallet"}.`;
    if (total.value <= 0 && form.items.length) formError.value = "The total can't be ₱0. Check the unit costs.";
  }
  return !Object.keys(err).length && !formError.value;
}

async function save(send) {
  if (!canSave.value) return toast.error(`You don't have permission to ${isEdit.value ? "edit" : "create"} funding requests`);
  if (!validate(send)) {
    toast.error("A few things need a look first.", { toastId: "fr-form" });
    await nextTick();
    document.querySelector(".fr .invalid, .fr .fr-empty.bad")?.scrollIntoView({ behavior: "smooth", block: "center" });
    return;
  }
  busy.value = send ? "send" : "draft";
  const payload = {
    approver_id: form.approver_id || null,
    items: form.items.map((l) => ({ product_id: l.product_id, quantity: Number(l.quantity), unit_cost: Number(l.unit_cost) })),
    required_delivery_date: form.required_delivery_date || null,
    urgency_level: form.urgency_level || null,
    reason_for_shortage: form.reason_for_shortage || null,
    business_justification: form.business_justification.trim() || null,
    additional_notes: form.additional_notes.trim() || null,
    payment_method: form.payment_method || null,
    payment_channel: form.payment_channel || null,
    logistics_cost: Number(form.logistics_cost) || 0,
  };
  try {
    const { data } = isEdit.value
      ? await api.put(`${BASE}/${editId.value}`, { ...payload, submit: send })
      : await api.post(BASE, { ...payload, is_draft: !send });
    toast.success(data.message || (send ? "Sent to Finance" : "Draft saved"));
    router.push(LIST);
  } catch (e) {
    const errs = e?.response?.data?.errors ?? {};
    for (const [k, v] of Object.entries(errs)) err[k] = Array.isArray(v) ? v[0] : v;
    formError.value = apiMessage(e, "Couldn't save the request.");
  } finally {
    busy.value = "";
  }
}

const goBack = () => (isEdit.value ? router.push({ name: "FundingRequestDetails", params: { id: editId.value } }) : router.push(LIST));

async function loadDraft() {
  const { data } = await api.get(`${BASE}/${editId.value}`);
  const r = data.data;
  if (r.request_status !== "Draft") {
    toast.info("This request was already sent to Finance, so it can't be changed.");
    router.replace({ name: "FundingRequestDetails", params: { id: r.id } });
    return false;
  }
  loadedNumber.value = r.finance_request_id;
  const byId = new Map(products.value.map((p) => [p.id, p]));
  let items = (r.items ?? []).map((i) => {
    const p = byId.get(i.product_id) ?? { id: i.product_id, product_name: i.product_name, quantity_in_stock: i.current_stock };
    return lineFor(p, { product_name: i.product_name, quantity: i.quantity, unit_cost: Number(i.unit_cost) });
  }).filter((l) => l.product_id);
  // Drafts saved before a request could hold several products
  if (!items.length && r.product_name) {
    const p = products.value.find((x) => x.product_name === r.product_name);
    if (p) items = [lineFor(p, { quantity: Number(r.requested_qty) || 1, unit_cost: Number(r.estimated_unit_cost ?? p.purchase_price) })];
  }
  Object.assign(form, {
    approver_id: r.approver_id || r.accounting_manager_id || "",
    items,
    required_delivery_date: r.required_delivery_date ? String(r.required_delivery_date).slice(0, 10) : "",
    urgency_level: r.urgency_level || "Normal",
    reason_for_shortage: r.reason_for_shortage || "",
    business_justification: r.business_justification || "",
    additional_notes: r.additional_notes || "",
    payment_method: r.payment_method || "bank",
    payment_channel: r.payment_channel || "",
    logistics_cost: Number(r.logistics_cost) || null,
  });
  return true;
}

async function init() {
  loadError.value = "";
  ready.value = false;
  try {
    const [prod, appr] = await Promise.all([
      api.get(`${BASE}/products`),
      api.get("/procurement/inventory/eligible-approvers").catch(() => ({ data: { data: [] } })),
    ]);
    products.value = prod.data?.data ?? [];
    approvers.value = appr.data?.data ?? [];
    if (isEdit.value && !(await loadDraft())) return;
    // A single approver is picked for you
    if (!form.approver_id && approvers.value.length === 1) form.approver_id = approvers.value[0].id;
    ready.value = true;
  } catch (e) {
    loadError.value = apiMessage(e, "Couldn't open the funding request form.");
  }
}

onMounted(init);
</script>

<style scoped>
.fr-sk {
  display: grid;
  gap: 16px;
}
.fr-sk .sp-card {
  display: grid;
  gap: 12px;
}
.fr {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 310px;
  gap: 16px;
  align-items: start;
}
.fr-main {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}
.fr-link {
  padding: 0;
  border: 0;
  background: none;
  color: var(--bc-accent-deep);
  font: inherit;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
}
.fr-empty {
  margin: 0;
  padding: 14px;
  border: 1px dashed var(--v-border-strong);
  border-radius: 12px;
  font-size: 13px;
  color: var(--v-muted);
  text-align: center;
  list-style: none;
}
.fr-empty.bad {
  border-color: var(--v-red-text);
  color: var(--v-red-text);
}

/* Product picker */
.fr-pick-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  margin-bottom: 10px;
}
.fr-search {
  position: relative;
  flex: 1 1 240px;
}
.fr-search > span {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  color: var(--v-muted);
  pointer-events: none;
}
.fr-search :deep(svg) {
  width: 16px;
  height: 16px;
}
.fr-search .sp-input {
  width: 100%;
  padding-left: 36px;
}
.fr-filters {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}
.fr-filter {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 36px;
  padding: 0 12px;
  border: 1px solid var(--v-border-strong);
  border-radius: 999px;
  background: var(--v-surface);
  color: var(--v-text-2);
  font: inherit;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.fr-filter small {
  color: var(--v-muted);
}
.fr-filter.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
}
.fr-picker {
  display: flex;
  flex-direction: column;
  gap: 4px;
  max-height: 300px;
  margin: 0;
  padding: 4px;
  overflow-y: auto;
  border: 1px solid var(--v-border);
  border-radius: 14px;
  list-style: none;
}
.fr-option {
  display: flex;
  align-items: center;
  gap: 12px;
  width: 100%;
  min-height: 52px;
  padding: 8px 12px;
  border: 1px solid transparent;
  border-radius: 10px;
  background: none;
  color: var(--v-text);
  font: inherit;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s, border-color 0.15s;
}
.fr-option:hover {
  background: var(--v-subtle);
}
.fr-option:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 1px;
}
.fr-option.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.fr-tick {
  flex: none;
  display: grid;
  place-items: center;
  width: 20px;
  height: 20px;
  border: 1.5px solid var(--v-border-strong);
  border-radius: 6px;
  background: var(--v-surface);
}
.fr-option.on .fr-tick {
  border-color: var(--bc-accent);
  background: var(--bc-accent);
  color: #fff;
}
.fr-tick :deep(svg) {
  width: 13px;
  height: 13px;
}
.fr-option-name {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.fr-option-name b {
  font-size: 13.5px;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.fr-option-name small {
  font-size: 12px;
  color: var(--v-muted);
}
.fr-stock {
  flex: none;
  white-space: nowrap;
}
.fr-option-cost {
  flex: none;
  min-width: 84px;
  font-size: 13px;
  font-weight: 600;
  text-align: right;
  font-variant-numeric: tabular-nums;
}

/* Picked lines */
.fr-lines {
  margin-top: 18px;
}
.fr-lines-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}
.fr-line {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 150px 150px 110px auto;
  gap: 10px 12px;
  align-items: end;
  padding: 12px 14px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
}
.fr-line + .fr-line {
  margin-top: 8px;
}
.fr-line-name {
  align-self: center;
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.fr-line-name b {
  font-size: 13.5px;
  color: var(--v-text);
}
.fr-line-name small {
  font-size: 12px;
  color: var(--v-muted);
}
.fr-affix {
  position: relative;
  display: flex;
  align-items: center;
}
.fr-affix .sp-input {
  width: 100%;
  padding-right: 64px;
}
.fr-affix em {
  position: absolute;
  right: 12px;
  font-size: 12px;
  font-style: normal;
  color: var(--v-muted);
  pointer-events: none;
}
.fr-affix.pre .sp-input {
  padding-right: 12px;
  padding-left: 28px;
}
.fr-affix.pre em {
  right: auto;
  left: 12px;
}
.fr-line-total {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.fr-line-total b {
  display: flex;
  align-items: center;
  min-height: 42px;
  font-size: 14px;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.fr-remove {
  margin-bottom: 3px;
}
.fr-note {
  grid-column: 1 / -1;
  display: flex;
  align-items: flex-start;
  gap: 6px;
  margin: 0;
  font-size: 12px;
  color: var(--v-amber-text);
}
.fr-note.bad {
  color: var(--v-red-text);
}
.fr-note :deep(svg) {
  flex: none;
  width: 14px;
  height: 14px;
  margin-top: 1px;
}

/* Urgency */
.fr-fieldset {
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}
.fr-fieldset legend {
  margin-bottom: 6px;
  padding: 0;
}
.fr-seg {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}
.fr-seg-opt,
.fr-method,
.fr-channel {
  position: relative;
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.fr-seg-opt input,
.fr-method input,
.fr-channel input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.fr-seg-opt {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 12px;
  border: 1px solid var(--v-border-strong);
  border-radius: 12px;
  background: var(--v-surface);
}
.fr-seg-opt b {
  font-size: 13px;
  color: var(--v-text);
}
.fr-seg-opt small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.fr-seg-opt.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.fr-seg-opt.on.amber {
  border-color: var(--v-amber-text);
  background: var(--v-amber-soft);
}
.fr-seg-opt.on.red {
  border-color: var(--v-red-text);
  background: var(--v-red-soft);
}
.fr-seg-opt:has(input:focus-visible),
.fr-method:has(input:focus-visible),
.fr-channel:has(input:focus-visible) {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}
.fr-count {
  align-self: flex-end;
}

/* Payment */
.fr-methods {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
.fr-method {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border: 1px solid var(--v-border-strong);
  border-radius: 14px;
  background: var(--v-surface);
}
.fr-method > span:last-child {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.fr-method b {
  font-size: 13.5px;
  color: var(--v-text);
}
.fr-method small {
  font-size: 12px;
  color: var(--v-muted);
}
.fr-method.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.fr-method.invalid {
  border-color: var(--v-red-text);
}
.fr-channels-wrap {
  margin-top: 16px;
}
.fr-channels {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.fr-channel {
  display: inline-flex;
  align-items: center;
  min-height: 38px;
  padding: 0 14px;
  border: 1px solid var(--v-border-strong);
  border-radius: 999px;
  background: var(--v-surface);
  color: var(--v-text-2);
  font-size: 13px;
  font-weight: 500;
}
.fr-channel.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-weight: 600;
}
.fr-delivery {
  max-width: 260px;
  margin-top: 16px;
}

/* Side */
.fr-side {
  min-width: 0;
}
.fr-sticky {
  position: sticky;
  top: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.fr-sum {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 12px;
  border-radius: 12px;
  background: var(--v-subtle);
}
.fr-sum div {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 13px;
}
.fr-sum dt {
  color: var(--v-muted);
}
.fr-sum dd {
  margin: 0;
  font-weight: 600;
  color: var(--v-text);
  font-variant-numeric: tabular-nums;
}
.fr-sum .total {
  padding-top: 8px;
  border-top: 1px solid var(--v-border);
}
.fr-sum .total dd {
  font-size: 18px;
}
.fr-paynote {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.fr-paynote :deep(svg) {
  width: 16px;
  height: 16px;
  color: var(--bc-accent-deep);
}
.fr-btn {
  justify-content: center;
  width: 100%;
}

@media (prefers-reduced-motion: reduce) {
  .fr-option,
  .fr-filter,
  .fr-seg-opt,
  .fr-method,
  .fr-channel {
    transition: none;
  }
}
@media (max-width: 1000px) {
  .fr {
    grid-template-columns: minmax(0, 1fr);
  }
  .fr-sticky {
    position: static;
  }
}
@media (max-width: 720px) {
  .fr-line {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }
  .fr-line-name {
    grid-column: 1 / -1;
  }
  .fr-line-total {
    grid-column: 1;
  }
  .fr-remove {
    grid-column: 2;
    justify-self: end;
  }
  .fr-seg,
  .fr-methods {
    grid-template-columns: minmax(0, 1fr);
  }
  .fr-option-cost {
    display: none;
  }
  .fr-delivery {
    max-width: none;
  }
}
</style>
