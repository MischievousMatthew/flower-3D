<template>
  <HrPage :title="editId ? `Edit ${loadedNumber || 'draft'}` : 'New purchase order'" :subtitle="editId ? 'Change the order before it goes to the supplier.' : 'Pick a supplier, add what you need at the agreed price, and save it as a draft, send it for approval, or send it now.'">
    <template #actions>
      <router-link :to="editId ? { name: 'OrderDetail', params: { id: editId } } : { name: 'OrderList' }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.chevronLeft"></span>{{ editId ? "Back to order" : "All orders" }}</router-link>
    </template>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="init">Try again</button>
    </div>

    <div v-if="!ready && !loadError" class="co-sk">
      <div class="sp-card"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-block"></div></div>
      <div class="sp-card"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-block"></div></div>
    </div>

    <form v-else-if="ready" class="co" novalidate @submit.prevent>
      <div class="co-main">
        <!-- Supplier and dates -->
        <section class="sp-card" aria-labelledby="co-sup">
          <div class="sp-card-head"><div><h2 id="co-sup">Supplier &amp; dates</h2><p>Who it's for and when you need it</p></div></div>
          <div class="sp-grid-2">
            <label class="sp-field sp-span-all">
              <span class="sp-label">Supplier</span>
              <select v-model="form.supplier_id" class="sp-select" :class="{ invalid: err.supplier_id }" @change="pickSupplier">
                <option value="">Choose an active supplier</option>
                <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.company_name }}{{ typeOf(s.supplier_type) ? ` · ${typeOf(s.supplier_type).label}` : "" }}</option>
              </select>
              <small v-if="err.supplier_id" class="sp-error">{{ err.supplier_id }}</small>
              <small v-else-if="!suppliers.length" class="sp-hint">No active suppliers yet. <router-link :to="{ name: 'SupplierCreate' }">Add one</router-link> first.</small>
            </label>
            <div v-if="supplier" class="co-supcard sp-span-all">
              <span class="co-logo">{{ initials(supplier.company_name) }}</span>
              <div class="grow">
                <b>{{ supplier.company_name }}</b>
                <small>{{ [daysText(supplier.delivery_days) && `Delivers ${daysText(supplier.delivery_days)}`, leadText(supplier.lead_time_days) && `${leadText(supplier.lead_time_days)} lead time`, supplier.minimum_order && `minimum ${money(supplier.minimum_order)}`, supplier.payment_terms].filter(Boolean).join(" · ") || "No delivery schedule set" }}</small>
              </div>
              <router-link :to="{ name: 'SupplierProfile', params: { id: supplier.id } }" class="sp-btn sp-btn-ghost sp-btn-sm">Profile</router-link>
            </div>
            <label class="sp-field">
              <span class="sp-label">Order date</span>
              <input v-model="form.order_date" type="date" class="sp-input" :class="{ invalid: err.order_date }" />
              <small v-if="err.order_date" class="sp-error">{{ err.order_date }}</small>
            </label>
            <label class="sp-field">
              <span class="sp-label">Expected delivery <small>(optional)</small></span>
              <input v-model="form.expected_date" type="date" class="sp-input" :class="{ invalid: err.expected_date }" :min="form.order_date || undefined" />
              <small v-if="err.expected_date" class="sp-error">{{ err.expected_date }}</small>
              <small v-else-if="suggested && suggested !== form.expected_date" class="sp-hint">From their lead time and delivery days: <button type="button" class="co-link" @click="form.expected_date = suggested">use {{ poDate(suggested) }}</button></small>
            </label>
          </div>
        </section>

        <!-- Items -->
        <section class="sp-card" aria-labelledby="co-items">
          <div class="sp-card-head">
            <div><h2 id="co-items">What you're ordering</h2><p>Flowers and supplies, how many, and the price you agreed on</p></div>
            <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="addLine()"><span v-html="ICONS.plus"></span>Add a line</button>
          </div>

          <div v-if="catalog.length" class="co-catalog">
            <span class="sp-label">From {{ supplier.company_name }}'s catalog</span>
            <div class="co-chips">
              <button v-for="c in catalog" :key="c.id" type="button" class="co-chip" :disabled="!c.is_available" :title="c.is_available ? `Add ${c.name}` : 'Not available right now'" @click="addFromCatalog(c)">
                <span v-html="ICONS.plus"></span>{{ c.name }}<small>{{ money(c.price) }} {{ unitLabel(c.unit) }}</small>
              </button>
            </div>
          </div>

          <p v-if="!form.items.length" class="sp-hint co-empty">No lines yet. Add one, or pick from the supplier's catalog above.</p>
          <div v-for="(line, i) in form.items" :key="line._key" class="co-line">
            <div class="co-line-grid">
              <label class="sp-field co-name">
                <span class="sp-label">Item</span>
                <input v-model.trim="line.product_name" class="sp-input" :class="{ invalid: err[`items.${i}.product_name`] }" maxlength="255" :list="`co-cat-${line._key}`" placeholder="e.g. Red rose, 50 cm" @change="matchCatalog(line)" />
                <datalist :id="`co-cat-${line._key}`"><option v-for="c in catalog" :key="c.id" :value="c.name" /></datalist>
                <small v-if="err[`items.${i}.product_name`]" class="sp-error">{{ err[`items.${i}.product_name`] }}</small>
              </label>
              <label class="sp-field co-stock">
                <span class="sp-label">Goes into stock as</span>
                <select v-model="line.product_id" class="sp-select" :class="{ invalid: err[`items.${i}.product_id`] }">
                  <option :value="null">Not linked yet</option>
                  <option v-for="p in stock" :key="p.id" :value="p.id">{{ p.product_name }}{{ p.sku ? ` (${p.sku})` : "" }}</option>
                </select>
                <small v-if="err[`items.${i}.product_id`]" class="sp-error">{{ err[`items.${i}.product_id`] }}</small>
              </label>
              <label class="sp-field co-qty">
                <span class="sp-label">Quantity</span>
                <input v-model.number="line.quantity" class="sp-input" :class="{ invalid: err[`items.${i}.quantity`] }" type="number" min="1" step="1" inputmode="numeric" />
                <small v-if="err[`items.${i}.quantity`]" class="sp-error">{{ err[`items.${i}.quantity`] }}</small>
              </label>
              <label class="sp-field co-unit">
                <span class="sp-label">Unit</span>
                <select v-model="line.unit" class="sp-select">
                  <option v-for="u in UNITS" :key="u.value" :value="u.value">{{ u.value }}</option>
                </select>
              </label>
              <label class="sp-field co-price">
                <span class="sp-label">Agreed price (₱)</span>
                <input v-model.number="line.price" class="sp-input" :class="{ invalid: err[`items.${i}.price`] }" type="number" min="0" step="0.01" inputmode="decimal" />
                <small v-if="err[`items.${i}.price`]" class="sp-error">{{ err[`items.${i}.price`] }}</small>
              </label>
              <div class="co-amount">
                <span class="sp-label">Amount</span>
                <b>{{ money((Number(line.quantity) || 0) * (Number(line.price) || 0)) }}</b>
              </div>
              <button type="button" class="sp-icon-btn co-remove" :aria-label="`Remove line ${i + 1}`" @click="form.items.splice(i, 1)"><span v-html="ICONS.trash"></span></button>
            </div>
            <p v-if="lineNote(line)" class="co-note" :class="lineNote(line).tone"><span v-html="ICONS.alert"></span>{{ lineNote(line).text }}</p>
          </div>
          <small v-if="err.items" class="sp-error">{{ err.items }}</small>
        </section>

        <section class="sp-card" aria-labelledby="co-notes">
          <div class="sp-card-head"><div><h2 id="co-notes">Notes for the supplier</h2><p>Shown on the order they receive</p></div></div>
          <textarea v-model.trim="form.notes" class="sp-textarea" rows="3" maxlength="2000" placeholder="Delivery time, packing, grade, who to call on arrival…" aria-label="Notes"></textarea>
        </section>
      </div>

      <!-- Summary and actions -->
      <aside class="co-side">
        <div class="sp-card co-sticky">
          <span class="sp-label">Summary</span>
          <dl class="co-sum">
            <div><dt>Lines</dt><dd>{{ form.items.length }}</dd></div>
            <div><dt>Units</dt><dd>{{ totalUnits.toLocaleString("en-PH") }}</dd></div>
            <div class="total"><dt>Total</dt><dd>{{ money(total) }}</dd></div>
          </dl>
          <p v-if="belowMinimum" class="co-note amber"><span v-html="ICONS.alert"></span>{{ supplier.company_name }} asks for at least {{ money(supplier.minimum_order) }} per order.</p>

          <label v-if="canApprove && supplier?.email" class="co-check">
            <input v-model="sendEmail" type="checkbox" />
            <span>Email the order to {{ supplier.email }} when it's approved</span>
          </label>

          <p v-if="formError" class="sp-error" role="alert">{{ formError }}</p>
          <button v-if="canApprove" type="button" class="sp-btn sp-btn-primary co-btn" :disabled="!!busy" @click="save('approve')">{{ busy === "approve" ? "Sending…" : "Approve & send" }}</button>
          <button type="button" class="sp-btn co-btn" :class="canApprove ? 'sp-btn-ghost' : 'sp-btn-primary'" :disabled="!!busy" @click="save('submit')">{{ busy === "submit" ? "Sending…" : "Send for approval" }}</button>
          <button type="button" class="sp-btn sp-btn-ghost co-btn" :disabled="!!busy" @click="save('draft')">{{ busy === "draft" ? "Saving…" : "Save as draft" }}</button>
          <small class="sp-hint">{{ canApprove ? "Approving sends it to the supplier. A draft waits until you're ready." : "Someone who can approve orders checks it before it goes to the supplier." }}</small>
        </div>
      </aside>
    </form>
  </HrPage>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import HrPage from "../../../../../components/hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { money } from "../../../../../components/vendor/finance/financeUi";
import { typeOf, daysText, leadText, initials, UNITS, unitLabel, DAYS } from "../../../../../components/procurement/supplierUi";
import { poDate, todayYmd, isEditable } from "../../../../../components/procurement/poUi";
import { useAssignment } from "../../../../../composables/useAssignment";
import { orderService } from "../../../../../services/orderService";
import { supplierService } from "../../../../../services/supplierService";
import { toast } from "@/utils/snackbar";
import { linkedId } from "@/utils/hiddenIds";
import "../../../../../components/vendor/finance/finance.css";

const route = useRoute();
const router = useRouter();
const { can } = useAssignment();
const canApprove = computed(() => can("sc_orders", "approve"));

// The order being edited: the router keeps its ID out of the address bar
const editId = ref(linkedId("order"));
const ready = ref(false);
const loadError = ref("");
const loadedNumber = ref("");
const suppliers = ref([]);
const supplierInfo = ref(null);
const catalog = ref([]);
const stock = ref([]);
const busy = ref("");
const formError = ref("");
const sendEmail = ref(true);
const err = reactive({});

let keySeed = 0;
const blankLine = (extra = {}) => ({ _key: ++keySeed, product_name: "", product_id: null, quantity: 1, unit: "stem", price: 0, supplier_product_id: null, ...extra });
const form = reactive({ supplier_id: "", order_date: todayYmd(), expected_date: "", notes: "", items: [] });

const supplier = computed(() => supplierInfo.value ?? suppliers.value.find((s) => s.id === form.supplier_id) ?? null);
const total = computed(() => form.items.reduce((s, l) => s + (Number(l.quantity) || 0) * (Number(l.price) || 0), 0));
const totalUnits = computed(() => form.items.reduce((s, l) => s + (Number(l.quantity) || 0), 0));
const belowMinimum = computed(() => supplier.value?.minimum_order && form.items.length && total.value < Number(supplier.value.minimum_order));

// Earliest delivery day once the promised lead time has passed
const suggested = computed(() => {
  const lead = supplier.value?.lead_time_days;
  if (lead === null || lead === undefined || !form.order_date) return "";
  const d = new Date(`${form.order_date}T00:00:00`);
  d.setDate(d.getDate() + Number(lead));
  const days = supplier.value.delivery_days ?? [];
  for (let i = 0; days.length && i < 7 && !days.includes(DAYS[(d.getDay() + 6) % 7]); i++) d.setDate(d.getDate() + 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
});

function lineNote(line) {
  const c = catalog.value.find((x) => x.id === line.supplier_product_id);
  if (c?.min_qty && Number(line.quantity) < c.min_qty) return { tone: "amber", text: `${supplier.value.company_name} sells at least ${c.min_qty} ${c.unit}s of this.` };
  if (c && Number(line.price) !== Number(c.price)) return { tone: "slate", text: `Their catalog price is ${money(c.price)} ${unitLabel(c.unit)}.` };
  if (!line.product_id && line.product_name) return { tone: "slate", text: "Link it to a stock item so it can be received into the warehouse." };
  return null;
}

const stockMatch = (name) => {
  const n = (name || "").toLowerCase();
  return stock.value.find((p) => p.product_name?.toLowerCase() === n)?.id
    ?? stock.value.find((p) => n && (n.includes(p.product_name?.toLowerCase()) || p.product_name?.toLowerCase().includes(n)))?.id
    ?? null;
};

function addLine(extra = {}) {
  form.items.push(blankLine(extra));
}
function addFromCatalog(c) {
  const existing = form.items.find((l) => l.supplier_product_id === c.id);
  if (existing) {
    existing.quantity = Number(existing.quantity || 0) + (c.min_qty || 1);
    return;
  }
  // An untouched empty line is used instead of leaving it behind
  const blank = form.items.findIndex((l) => !l.product_name && !Number(l.price));
  if (blank >= 0) form.items.splice(blank, 1);
  addLine({ product_name: c.name, unit: c.unit, price: Number(c.price), quantity: c.min_qty || 1, supplier_product_id: c.id, product_id: stockMatch(c.name) });
}
function matchCatalog(line) {
  const c = catalog.value.find((x) => x.name.toLowerCase() === line.product_name.toLowerCase());
  if (c) Object.assign(line, { unit: c.unit, price: Number(c.price), supplier_product_id: c.id, quantity: Math.max(Number(line.quantity) || 1, c.min_qty || 1) });
  if (!line.product_id) line.product_id = stockMatch(line.product_name);
}

async function pickSupplier() {
  supplierInfo.value = null;
  catalog.value = [];
  if (!form.supplier_id) return;
  try {
    const res = await supplierService.profile(form.supplier_id);
    supplierInfo.value = res.data.data.supplier;
    catalog.value = res.data.data.catalog ?? [];
    if (!form.expected_date && suggested.value) form.expected_date = suggested.value;
  } catch {
    // The basic list details are still shown
  }
}

function validate(action) {
  Object.keys(err).forEach((k) => delete err[k]);
  if (!form.supplier_id) err.supplier_id = "Choose who the order is for.";
  if (!form.order_date) err.order_date = "Pick the order date.";
  if (form.expected_date && form.order_date && form.expected_date < form.order_date) err.expected_date = "It can't arrive before it's ordered.";
  if (!form.items.length) err.items = "Add at least one line.";
  form.items.forEach((l, i) => {
    if (!l.product_name) err[`items.${i}.product_name`] = "Name the item.";
    if (!(Number(l.quantity) >= 1)) err[`items.${i}.quantity`] = "1 or more.";
    if (!(Number(l.price) >= 0) || l.price === "") err[`items.${i}.price`] = "Enter the price.";
    // Received stock needs to know which product it is
    if (action !== "draft" && !l.product_id) err[`items.${i}.product_id`] = "Pick the stock item, so it can be received.";
  });
  return !Object.keys(err).length;
}

async function save(action) {
  formError.value = "";
  if (!validate(action)) {
    toast.error("A few things need a look before saving.", { toastId: "po-form" });
    await nextTick();
    document.querySelector(".co .invalid")?.focus();
    return;
  }
  busy.value = action;
  const payload = {
    supplier_id: form.supplier_id,
    order_date: form.order_date,
    expected_date: form.expected_date || null,
    notes: form.notes || null,
    items: form.items.map(({ product_name, product_id, quantity, unit, price, supplier_product_id }) => ({ product_name, product_id, quantity: Number(quantity), unit, price: Number(price), supplier_product_id })),
  };
  try {
    let order;
    if (editId.value) {
      order = (await orderService.update(editId.value, payload)).data;
      if (action === "submit" && order.status === "draft") order = (await orderService.submit(order.id)).data;
      if (action === "approve") order = (await orderService.approve(order.id, { send_email: sendEmail.value && !!supplier.value?.email })).data;
    } else {
      order = (await orderService.create({ ...payload, action, send_email: action === "approve" && sendEmail.value && !!supplier.value?.email })).data;
    }
    const done = {
      draft: "Saved as a draft.",
      submit: "Sent for approval.",
      approve: order.emailed ? `Approved and emailed to ${supplier.value.email}.` : order.emailed === false ? "Approved. The email didn't go through; you can send it again from the order." : "Approved and sent to the supplier.",
    }[action];
    toast.success(`${order.order_number}: ${done}`);
    router.push({ name: "OrderDetail", params: { id: order.id } });
  } catch (e) {
    const errs = e?.response?.data?.errors ?? {};
    for (const [k, v] of Object.entries(errs)) err[k] = v[0];
    formError.value = apiMessage(e, "Couldn't save the order.");
  } finally {
    busy.value = "";
  }
}

async function init() {
  loadError.value = "";
  ready.value = false;
  try {
    const [sup, items] = await Promise.all([supplierService.list({ status: "active", per_page: 100 }), orderService.stockItems().catch(() => ({ data: { data: [] } }))]);
    suppliers.value = sup.data?.data ?? [];
    stock.value = items.data?.data ?? [];

    if (editId.value) {
      const o = (await orderService.find(editId.value)).data;
      if (!isEditable(o.status)) {
        toast.info("This order was already sent to the supplier, so it can't be changed.");
        router.replace({ name: "OrderDetail", params: { id: o.id } });
        return;
      }
      loadedNumber.value = o.order_number;
      Object.assign(form, {
        supplier_id: o.supplier_id,
        order_date: (o.order_date || o.created_at || todayYmd()).slice(0, 10),
        expected_date: o.expected_date ? o.expected_date.slice(0, 10) : "",
        notes: o.notes ?? "",
        items: (o.items ?? []).map((i) => blankLine({ product_name: i.product_name, product_id: i.product_id, quantity: i.quantity, unit: i.unit || "stem", price: Number(i.price), supplier_product_id: i.supplier_product_id })),
      });
      if (!suppliers.value.some((s) => s.id === o.supplier_id) && o.supplier) suppliers.value.unshift(o.supplier);
      await pickSupplier();
    } else if (route.query.supplier) {
      form.supplier_id = Number(route.query.supplier);
      await pickSupplier();
    } else if (history.state?.prefill) {
      // From Stock & replenishment: the suggested amounts, at the last price paid
      try {
        const prefill = JSON.parse(history.state.prefill);
        if (prefill.supplier_id && suppliers.value.some((s) => s.id === prefill.supplier_id)) {
          form.supplier_id = prefill.supplier_id;
          await pickSupplier();
        }
        form.items = (prefill.lines ?? []).map((l) => blankLine({ product_name: l.product_name, product_id: l.product_id, quantity: l.quantity, unit: l.unit || "stem", price: Number(l.price) || 0 }));
      } catch {
        // A broken prefill just means starting from a blank order
      }
    }
    if (!form.items.length && !editId.value) addLine();
    ready.value = true;
  } catch (e) {
    loadError.value = apiMessage(e, "Couldn't open the order form.");
  }
}

onMounted(init);
</script>

<style scoped>
.co-sk {
  display: grid;
  gap: 16px;
}
.co-sk .sp-card {
  display: grid;
  gap: 12px;
}
.co {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 16px;
  align-items: start;
}
.co-main {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.co-supcard {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 14px;
  background: var(--v-subtle);
}
.co-supcard b {
  font-size: 13.5px;
  color: var(--v-text);
}
.co-supcard small {
  font-size: 12px;
  color: var(--v-muted);
}
.co-logo {
  flex: none;
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 11px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-size: 12.5px;
  font-weight: 600;
}
.co-link {
  padding: 0;
  border: 0;
  background: none;
  color: var(--bc-accent-deep);
  font: inherit;
  font-weight: 600;
  text-decoration: underline;
  cursor: pointer;
}

/* Catalog */
.co-catalog {
  margin-bottom: 14px;
}
.co-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 6px;
}
.co-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 36px;
  padding: 0 12px;
  border: 1px solid var(--v-border-strong);
  border-radius: 999px;
  background: var(--v-surface);
  color: var(--v-text);
  font: inherit;
  font-size: 12.5px;
  font-weight: 500;
  cursor: pointer;
}
.co-chip:hover:not(:disabled) {
  border-color: var(--bc-accent);
}
.co-chip:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}
.co-chip small {
  color: var(--v-muted);
  font-weight: 400;
}
.co-chip :deep(svg) {
  width: 13px;
  height: 13px;
  color: var(--bc-accent-deep);
}

/* Lines */
.co-empty {
  margin: 4px 0 8px;
}
.co-line {
  padding: 14px;
  border: 1px solid var(--v-border);
  border-radius: 14px;
}
.co-line + .co-line {
  margin-top: 10px;
}
/* Two rows: what it is, then how many and for how much */
.co-line-grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 10px;
  align-items: start;
}
.co-name { grid-column: span 7; }
.co-stock { grid-column: span 5; }
.co-qty { grid-column: span 2; }
.co-unit { grid-column: span 2; }
.co-price { grid-column: span 3; }
.co-amount { grid-column: span 4; }
.co-remove { grid-column: span 1; justify-self: end; }
.co-amount {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.co-amount b {
  display: flex;
  align-items: center;
  min-height: 42px;
  font-size: 14px;
  color: var(--v-text);
}
.co-remove {
  margin-top: 24px;
}
.co-note {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  margin: 8px 0 0;
  font-size: 12px;
  color: var(--v-muted);
}
.co-note.amber {
  color: var(--v-amber-text);
}
.co-note :deep(svg) {
  flex: none;
  width: 14px;
  height: 14px;
  margin-top: 1px;
}

/* Side */
.co-side {
  min-width: 0;
}
.co-sticky {
  position: sticky;
  top: 16px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.co-sum {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 12px;
  border-radius: 12px;
  background: var(--v-subtle);
}
.co-sum div {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
}
.co-sum dt {
  color: var(--v-muted);
}
.co-sum dd {
  margin: 0;
  font-weight: 600;
  color: var(--v-text);
}
.co-sum .total {
  padding-top: 8px;
  border-top: 1px solid var(--v-border);
}
.co-sum .total dd {
  font-size: 18px;
}
.co-check {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  font-size: 12.5px;
  color: var(--v-text-2);
  cursor: pointer;
}
.co-check input {
  width: 18px;
  height: 18px;
  margin-top: 1px;
  accent-color: var(--bc-accent);
}
.co-btn {
  justify-content: center;
  width: 100%;
}

@media (max-width: 1000px) {
  .co {
    grid-template-columns: minmax(0, 1fr);
  }
  .co-sticky {
    position: static;
  }
}
@media (max-width: 640px) {
  .co-line-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .co-name,
  .co-stock {
    grid-column: span 2;
  }
  .co-qty,
  .co-unit,
  .co-price,
  .co-amount {
    grid-column: span 1;
  }
  .co-remove {
    grid-column: span 2;
    margin-top: 0;
  }
}
</style>
