<template>
  <ShopLayout section="finance" title="Expenses" subtitle="Record what it costs to run your shop. Payroll, refunds and stock purchases are added for you.">
    <template #actions>
      <RangePicker @change="load" />
      <button type="button" class="sp-btn sp-btn-primary" @click="openForm()"><span v-html="ICONS.plus"></span>Record expense</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Summary -->
    <section class="fx-kpis" aria-label="Costs this period">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article class="sp-card fx-kpi">
          <span class="fx-kpi-label"><i class="dot amber"></i>Recorded expenses</span>
          <strong>{{ money(data.total) }}</strong>
          <span class="fx-kpi-sub">
            <span v-if="trend !== null" class="fx-trend" :class="trend > 0 ? 'down' : trend < 0 ? 'up' : 'flat'">{{ trend > 0 ? "▲" : trend < 0 ? "▼" : "•" }} {{ Math.abs(trend) }}%</span>
            {{ data.expenses.length }} {{ data.expenses.length === 1 ? "entry" : "entries" }}
          </span>
        </article>
        <article v-for="a in data.automatic" :key="a.key" class="sp-card fx-kpi">
          <span class="fx-kpi-label"><i class="dot" :class="AUTO_TONE[a.key]"></i>{{ a.label }}</span>
          <strong>{{ money(a.amount) }}</strong>
          <span class="fx-kpi-sub">{{ a.hint }} · automatic</span>
        </article>
      </template>
    </section>

    <div class="ex-layout">
      <!-- List -->
      <section class="sp-card ex-list-card" aria-label="Recorded expenses">
        <div class="sp-toolbar ex-toolbar">
          <select v-model="category" class="sp-select ex-cat" aria-label="Category">
            <option value="">All categories</option>
            <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
          <label class="sp-search">
            <span v-html="ICONS.search"></span>
            <input v-model="search" class="sp-input" type="search" placeholder="Description, payee or reference" aria-label="Search expenses" />
          </label>
        </div>

        <ul v-if="!data" class="ex-list">
          <li v-for="n in 5" :key="n" class="ex-row"><div class="bc-skeleton fx-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
        </ul>
        <div v-else-if="!visible.length" class="sp-empty">
          <span class="sp-empty-icon" v-html="ICONS.card"></span>
          <h3>{{ data.expenses.length ? "Nothing matches" : "No expenses recorded" }}</h3>
          <p>{{ data.expenses.length ? "Try another category or search." : "Add rent, utilities, packaging and other running costs so your profit is accurate." }}</p>
          <button v-if="!data.expenses.length" type="button" class="sp-btn sp-btn-primary" @click="openForm()"><span v-html="ICONS.plus"></span>Record expense</button>
        </div>
        <ul v-else class="ex-list">
          <li v-for="x in visible" :key="x.id" class="ex-row">
            <span class="fx-icon" :class="`tone-${CAT_TONE[x.category] ?? 'slate'}`" v-html="ICONS[CAT_ICON[x.category] ?? 'card']"></span>
            <div class="ex-main">
              <strong>{{ x.description }}</strong>
              <span>{{ x.category_label }}<template v-if="x.payee"> · {{ x.payee }}</template><template v-if="x.payment_label"> · {{ x.payment_label }}</template></span>
              <small>{{ formatDate(x.spent_on) }}<template v-if="x.reference"> · Ref {{ x.reference }}</template></small>
            </div>
            <a v-if="x.receipt_url" :href="x.receipt_url" target="_blank" rel="noopener" class="ex-receipt" :aria-label="`Receipt for ${x.description}`">
              <img :src="x.receipt_url" alt="" loading="lazy" />
            </a>
            <span class="ex-amt">−{{ money(x.amount) }}</span>
            <div class="ex-actions">
              <button type="button" class="sp-icon-btn" :aria-label="`Edit ${x.description}`" @click="openForm(x)"><span v-html="ICONS.edit"></span></button>
              <button type="button" class="sp-icon-btn danger" :aria-label="`Delete ${x.description}`" @click="deleting = x"><span v-html="ICONS.trash"></span></button>
            </div>
          </li>
        </ul>
      </section>

      <!-- Breakdown -->
      <aside class="ex-side">
        <section class="sp-card" aria-label="By category">
          <div class="sp-card-head"><div><h2>By category</h2><p>Recorded expenses this period</p></div></div>
          <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
          <p v-else-if="!data.by_category.length" class="sp-hint">Nothing recorded yet.</p>
          <ul v-else class="cat-bars">
            <li v-for="c in data.by_category" :key="c.category">
              <div class="cat-top"><span>{{ c.label }}</span><b>{{ money(c.amount) }}</b></div>
              <div class="cat-bar"><span :class="`bg-${CAT_TONE[c.category] ?? 'slate'}`" :style="{ width: `${(c.amount / maxCat) * 100}%` }"></span></div>
            </li>
          </ul>
        </section>
        <section class="sp-card ex-tip" aria-label="Tip">
          <span class="fx-icon tone-green" v-html="ICONS.sparkle"></span>
          <p>Stock purchases aren't counted as an expense on Profit &amp; Loss. Their cost is counted when the flowers sell, so nothing is subtracted twice.</p>
        </section>
      </aside>
    </div>

    <!-- Form -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="form" class="sp-backdrop" @click="closeForm"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="form" class="sp-modal-wrap">
          <form class="sp-modal wide" role="dialog" aria-modal="true" aria-labelledby="ex-title" novalidate @submit.prevent="save">
            <div class="sp-modal-head">
              <div>
                <h3 id="ex-title">{{ form.id ? "Edit expense" : "Record an expense" }}</h3>
                <p>It's included in Profit &amp; Loss and your reports.</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeForm"><span v-html="ICONS.close"></span></button>
            </div>

            <div class="sp-field">
              <span class="sp-label">Category</span>
              <div class="ex-cats">
                <button v-for="c in categories" :key="c.value" type="button" class="sp-pick" :class="{ on: form.category === c.value }" @click="form.category = c.value">{{ c.label }}</button>
              </div>
              <small v-if="errors.category" class="sp-error">{{ errors.category }}</small>
            </div>

            <div class="sp-grid-2 ex-gap">
              <label class="sp-field sp-span-all">
                <span>What was it for?</span>
                <input v-model="form.description" class="sp-input" :class="{ invalid: errors.description }" maxlength="255" placeholder="e.g. October stall rent" />
                <small v-if="errors.description" class="sp-error">{{ errors.description }}</small>
              </label>
              <label class="sp-field">
                <span>Amount</span>
                <div class="money-in"><i>₱</i><input v-model.number="form.amount" type="number" min="0.01" step="0.01" class="sp-input" :class="{ invalid: errors.amount }" placeholder="0.00" /></div>
                <small v-if="errors.amount" class="sp-error">{{ errors.amount }}</small>
              </label>
              <label class="sp-field">
                <span>Date</span>
                <input v-model="form.spent_on" type="date" class="sp-input" :class="{ invalid: errors.spent_on }" :max="today" />
                <small v-if="errors.spent_on" class="sp-error">{{ errors.spent_on }}</small>
              </label>
              <label class="sp-field">
                <span>Paid with</span>
                <select v-model="form.payment_method" class="sp-select">
                  <option value="">Not set</option>
                  <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
              </label>
              <label class="sp-field">
                <span>Paid to <small>(optional)</small></span>
                <input v-model="form.payee" class="sp-input" maxlength="120" placeholder="e.g. Meralco, landlord" />
              </label>
              <label class="sp-field">
                <span>Reference <small>(optional)</small></span>
                <input v-model="form.reference" class="sp-input" maxlength="80" placeholder="Receipt or OR number" />
              </label>
              <div class="sp-field">
                <span class="sp-label">Receipt photo <small>(optional)</small></span>
                <div class="rc-row">
                  <img v-if="receiptPreview" :src="receiptPreview" alt="Receipt" class="rc-thumb" />
                  <label class="sp-btn sp-btn-ghost sp-btn-sm rc-file">
                    <input type="file" accept="image/jpeg,image/png,image/webp" @change="pickReceipt" />
                    <span v-html="ICONS.upload"></span>{{ receiptPreview ? "Change" : "Add photo" }}
                  </label>
                  <button v-if="receiptPreview" type="button" class="sp-btn sp-btn-sm link" @click="dropReceipt">Remove</button>
                </div>
                <small v-if="errors.receipt" class="sp-error">{{ errors.receipt }}</small>
              </div>
              <label class="sp-field sp-span-all">
                <span>Notes <small>(optional)</small></span>
                <textarea v-model="form.notes" class="sp-textarea" rows="2" maxlength="1000"></textarea>
              </label>
            </div>
            <p v-if="formError" class="sp-error">{{ formError }}</p>

            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="closeForm">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="saving">{{ saving ? "Saving…" : form.id ? "Save changes" : "Record expense" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Delete -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="deleting" class="sp-backdrop" @click="deleting = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="deleting" class="sp-modal-wrap">
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="exd-title">
            <div class="sp-modal-head">
              <div>
                <h3 id="exd-title">Delete this expense?</h3>
                <p>{{ deleting.description }} · {{ money(deleting.amount) }} on {{ formatDate(deleting.spent_on) }}</p>
              </div>
            </div>
            <p class="sp-hint">It's removed from Profit &amp; Loss and your reports. This can't be undone.</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="deleting = null">Cancel</button>
              <button type="button" class="sp-btn sp-btn-danger" :disabled="saving" @click="remove">{{ saving ? "Deleting…" : "Delete" }}</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { toast } from "@/utils/snackbar";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import RangePicker from "../../../components/vendor/finance/RangePicker.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { change, financeRange, formatDate, money, ymd } from "../../../components/vendor/finance/financeUi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/finance/finance.css";

const AUTO_TONE = { payroll: "violet", refunds: "red", stock: "blue" };
const CAT_TONE = { rent: "violet", utilities: "blue", supplies: "green", delivery: "amber", marketing: "pink", equipment: "teal", maintenance: "amber", fees: "slate", taxes: "red", other: "slate" };
const CAT_ICON = { rent: "store", utilities: "sparkle", supplies: "box", delivery: "truck", marketing: "globe", equipment: "cube", maintenance: "edit", fees: "card", taxes: "note", other: "card" };
const today = ymd(new Date());

const data = ref(null);
const error = ref("");
const category = ref("");
const search = ref("");
const form = ref(null);
const errors = reactive({});
const formError = ref("");
const saving = ref(false);
const deleting = ref(null);
const receiptFile = ref(null);
const receiptPreview = ref(null);
const removeReceipt = ref(false);

const categories = computed(() => data.value?.categories ?? []);
const paymentMethods = computed(() => data.value?.payment_methods ?? []);
const trend = computed(() => (data.value ? change(data.value.total, data.value.previous_total) : null));
const maxCat = computed(() => Math.max(1, ...(data.value?.by_category ?? []).map((c) => c.amount)));
const visible = computed(() => {
  const q = search.value.trim().toLowerCase();
  return (data.value?.expenses ?? []).filter(
    (x) => (!category.value || x.category === category.value)
      && (!q || [x.description, x.payee, x.reference].some((v) => v && v.toLowerCase().includes(q))),
  );
});

async function load() {
  error.value = "";
  try {
    const res = await api.get("vendor/books/expenses", { params: { from: financeRange.from, to: financeRange.to } });
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load your expenses.");
  }
}

function openForm(x = null) {
  Object.keys(errors).forEach((k) => delete errors[k]);
  formError.value = "";
  receiptFile.value = null;
  removeReceipt.value = false;
  receiptPreview.value = x?.receipt_url ?? null;
  form.value = x
    ? { id: x.id, category: x.category, description: x.description, amount: x.amount, spent_on: x.spent_on, payment_method: x.payment_method ?? "", payee: x.payee ?? "", reference: x.reference ?? "", notes: x.notes ?? "" }
    : { id: null, category: "supplies", description: "", amount: null, spent_on: today, payment_method: "cash", payee: "", reference: "", notes: "" };
}
function closeForm() {
  if (saving.value) return;
  if (receiptFile.value && receiptPreview.value) URL.revokeObjectURL(receiptPreview.value);
  form.value = null;
}
function pickReceipt(e) {
  delete errors.receipt;
  const file = e.target.files?.[0];
  e.target.value = "";
  if (!file) return;
  if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
    errors.receipt = "Use a JPG, PNG or WebP photo.";
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    errors.receipt = "The photo must be 5 MB or smaller.";
    return;
  }
  receiptFile.value = file;
  receiptPreview.value = URL.createObjectURL(file);
  removeReceipt.value = false;
}
function dropReceipt() {
  if (receiptFile.value) URL.revokeObjectURL(receiptPreview.value);
  receiptFile.value = null;
  receiptPreview.value = null;
  removeReceipt.value = true;
}

function validate() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  const f = form.value;
  if (!f.category) errors.category = "Pick a category.";
  if (!f.description.trim()) errors.description = "Say what it was for.";
  if (!(Number(f.amount) > 0)) errors.amount = "Enter an amount above ₱0.";
  if (!f.spent_on) errors.spent_on = "Pick the date.";
  else if (f.spent_on > today) errors.spent_on = "The date can't be in the future.";
  return !Object.keys(errors).length;
}

async function save() {
  if (saving.value || !validate()) return;
  saving.value = true;
  formError.value = "";
  try {
    const fd = new FormData();
    const f = form.value;
    for (const key of ["category", "description", "amount", "spent_on", "payment_method", "payee", "reference", "notes"]) {
      const v = typeof f[key] === "string" ? f[key].trim() : f[key];
      if (v !== "" && v !== null && v !== undefined) fd.append(key, v);
    }
    if (receiptFile.value) fd.append("receipt", receiptFile.value);
    if (f.id && removeReceipt.value && !receiptFile.value) fd.append("remove_receipt", "1");
    const { data: res } = await api.post(f.id ? `vendor/books/expenses/${f.id}` : "vendor/books/expenses", fd);
    toast.success(res.message);
    form.value = null;
    await load();
  } catch (e) {
    const fieldErrors = e?.response?.data?.errors ?? {};
    for (const [key, list] of Object.entries(fieldErrors)) errors[key] = [].concat(list)[0];
    formError.value = Object.keys(fieldErrors).length ? "" : apiMessage(e, "Couldn't save the expense.");
  } finally {
    saving.value = false;
  }
}

async function remove() {
  if (saving.value) return;
  saving.value = true;
  try {
    await api.delete(`vendor/books/expenses/${deleting.value.id}`);
    toast.success("Expense deleted.");
    deleting.value = null;
    await load();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't delete the expense."));
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
}
.ex-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 300px;
  gap: 16px;
  align-items: start;
}
.ex-list-card {
  padding: 16px;
}
.ex-toolbar {
  margin-bottom: 8px;
}
.ex-cat {
  width: auto;
  min-width: 170px;
}
.ex-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.ex-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 4px;
  border-top: 1px solid var(--v-border);
}
.ex-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.ex-main strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ex-main span {
  font-size: 12.5px;
  color: var(--v-text-2);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ex-main small {
  font-size: 11.5px;
  color: var(--v-faint);
}
.ex-receipt {
  flex: none;
}
.ex-receipt img {
  display: block;
  width: 36px;
  height: 36px;
  border-radius: 9px;
  object-fit: cover;
  border: 1px solid var(--v-border);
}
.ex-amt {
  flex: none;
  min-width: 96px;
  text-align: right;
  font-size: 14px;
  font-weight: 600;
  color: var(--v-red-text);
  white-space: nowrap;
}
.ex-actions {
  flex: none;
  display: flex;
  gap: 4px;
}
.sp-icon-btn.danger:hover {
  border-color: #feb2b2;
  color: var(--v-red-text);
}
.ex-side {
  position: sticky;
  top: 16px;
  display: grid;
  gap: 14px;
}
.cat-bars {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 12px;
}
.cat-top {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 5px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.cat-top span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.cat-top b {
  color: var(--v-text);
  white-space: nowrap;
}
.cat-bar {
  height: 7px;
  border-radius: 999px;
  background: var(--v-subtle-2);
  overflow: hidden;
}
.cat-bar span {
  display: block;
  height: 100%;
  border-radius: inherit;
}
.bg-violet { background: #b794f4; }
.bg-blue { background: #63b3ed; }
.bg-green { background: #48bb78; }
.bg-amber { background: #f6ad55; }
.bg-pink { background: #f687b3; }
.bg-teal { background: #4fd1c5; }
.bg-slate { background: #a0aec0; }
.bg-red { background: #fc8181; }
.ex-tip {
  display: flex;
  gap: 12px;
  background: var(--bc-accent-soft);
}
.ex-tip p {
  margin: 0;
  font-size: 12.5px;
  line-height: 1.55;
  color: var(--v-text-2);
}
.ex-cats {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.ex-gap {
  margin-top: 16px;
}
.money-in {
  position: relative;
}
.money-in i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-style: normal;
  color: var(--v-faint);
}
.money-in .sp-input {
  padding-left: 28px;
}
.rc-row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.rc-thumb {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  object-fit: cover;
  border: 1px solid var(--v-border);
}
.rc-file {
  position: relative;
}
.rc-file input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
}
.link {
  background: none;
  color: var(--v-red-text);
}
@media (max-width: 1100px) {
  .ex-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .ex-side {
    position: static;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 720px) {
  .ex-side {
    grid-template-columns: minmax(0, 1fr);
  }
  .ex-row {
    flex-wrap: wrap;
  }
  .ex-main {
    flex-basis: calc(100% - 52px);
  }
  .ex-receipt {
    margin-left: 52px;
  }
  .ex-amt {
    margin-left: auto;
    min-width: 0;
  }
  .ex-cat {
    flex: 1;
  }
}
</style>
