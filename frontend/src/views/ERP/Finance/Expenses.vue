<template>
  <HrPage title="Expenses" subtitle="Flowers, supplies, packaging, delivery, utilities, rent and everything else the shop pays for, with the receipts to show for it.">
    <template #actions>
      <RangePicker @change="load" />
      <button v-if="perms.create" type="button" class="sp-btn sp-btn-primary" @click="openForm()"><span v-html="ICONS.plus"></span>Record expense</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Headline numbers -->
    <section class="fx-kpis" aria-label="Expenses this period">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article v-for="k in kpis" :key="k.key" class="sp-card fx-kpi" :class="{ hero: k.hero }">
          <span class="fx-kpi-label"><span class="xp-ic" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>{{ k.label }}</span>
          <strong>{{ k.value }}</strong>
          <span class="fx-kpi-sub"><Trend v-if="k.trend" :now="data.total" :before="data.previous_total" cost /><span>{{ k.sub }}</span></span>
        </article>
      </template>
    </section>

    <div class="xp-two">
      <!-- By category -->
      <section class="sp-card" aria-label="By category">
        <div class="sp-card-head">
          <div><h2>By category</h2><p>What the money went on. Pick one to see just those.</p></div>
          <button v-if="category" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="setCategory('')">Show all</button>
        </div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <p v-else-if="!data.by_category.length" class="sp-hint">Nothing recorded in this period yet.</p>
        <ul v-else class="xp-cats">
          <li v-for="c in data.by_category" :key="c.category">
            <button type="button" class="xp-cat" :class="{ on: category === c.category, dim: category && category !== c.category }" @click="setCategory(category === c.category ? '' : c.category)">
              <span class="xp-cat-ic" :class="`tone-${look(c.category).tone}`" v-html="ICONS[look(c.category).icon]"></span>
              <span class="xp-cat-main">
                <span class="xp-cat-top"><b>{{ c.label }}</b><span v-if="c.stock" class="sp-chip blue">Stock</span><em>{{ money(c.amount) }}</em></span>
                <span class="xp-bar"><span :style="{ width: `${share(c.amount)}%`, background: look(c.category).color }"></span></span>
                <small>{{ countText(c.count, "expense") }} · {{ share(c.amount) }}%</small>
              </span>
            </button>
          </li>
        </ul>
      </section>

      <!-- Recorded automatically -->
      <aside class="sp-card xp-auto" aria-label="Recorded automatically">
        <div class="sp-card-head"><div><h2>Recorded for you</h2><p>Costs the books add on their own</p></div></div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <dl v-else>
          <div v-for="a in data.automatic" :key="a.key">
            <dt>{{ a.label }}<small>{{ a.hint }}</small></dt>
            <dd>{{ money(a.amount) }}</dd>
          </div>
        </dl>
        <p class="sp-hint">Flowers you record here count as stock, like approved funding requests: their cost reaches profit when the bouquets sell.</p>
      </aside>
    </div>

    <!-- List -->
    <section class="sp-card xp-list" aria-label="Expenses">
      <div class="sp-toolbar xp-toolbar">
        <select :value="category" class="sp-select" aria-label="Category" @change="setCategory($event.target.value)">
          <option value="">Every category</option>
          <option v-for="c in data?.categories ?? []" :key="c.value" :value="c.value">{{ c.label }}</option>
        </select>
        <label class="sp-search">
          <span v-html="ICONS.search"></span>
          <input v-model="search" class="sp-input" type="search" placeholder="What, who to, or reference" aria-label="Search expenses" />
        </label>
        <label class="xp-only">
          <input v-model="onlyMissing" type="checkbox" />
          Missing a receipt
        </label>
      </div>

      <div v-if="!data" class="xp-days">
        <div v-for="n in 4" :key="n" class="xp-row"><div class="bc-skeleton fx-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></div>
      </div>
      <div v-else-if="!shown.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.receipt"></span>
        <h3>{{ filtered ? "No expenses match" : "No expenses in this period" }}</h3>
        <p>{{ filtered ? "Try another category or search." : "Record flowers, packaging, rent and other costs as you pay them." }}</p>
        <button v-if="!filtered && perms.create" type="button" class="sp-btn sp-btn-primary" @click="openForm()">Record an expense</button>
      </div>
      <div v-else class="xp-days" :class="{ dim: loading }">
        <section v-for="day in days" :key="day.date" class="xp-day">
          <header class="xp-day-head"><span>{{ day.label }}</span><b>{{ money(day.total) }}</b></header>
          <article v-for="x in day.items" :key="x.id" class="xp-row">
            <span class="fx-icon" :class="`tone-${look(x.category).tone}`" v-html="ICONS[look(x.category).icon]"></span>
            <div class="grow">
              <strong>{{ x.description }}</strong>
              <span class="xp-meta">
                <span class="sp-chip" :class="look(x.category).tone">{{ x.category_label }}</span>
                <template v-if="x.payee">{{ x.payee }}</template>
                <template v-if="x.payment_label"> · {{ x.payment_label }}</template>
                <template v-if="x.reference"> · <span class="mono">{{ x.reference }}</span></template>
              </span>
              <span v-if="x.notes" class="xp-notes">{{ x.notes }}</span>
              <span class="xp-files">
                <button v-for="f in x.files" :key="f.id" type="button" class="xp-file" :title="`Open ${f.name}`" @click="openFile(x, f)">
                  <span v-html="ICONS[f.image ? 'image' : 'note']"></span>{{ f.name }}
                </button>
                <a v-if="x.receipt_url" class="xp-file" :href="x.receipt_url" target="_blank" rel="noopener"><span v-html="ICONS.image"></span>Receipt</a>
                <span v-if="!x.files.length && !x.receipt_url" class="xp-nofile">No receipt</span>
              </span>
            </div>
            <div class="xp-amt">
              <strong>{{ money(x.amount) }}</strong>
              <small v-if="x.stock">counts as stock</small>
              <small v-else-if="x.recorded_by">by {{ x.recorded_by }}</small>
            </div>
            <div v-if="perms.edit || perms.delete" class="xp-actions">
              <button v-if="perms.edit" type="button" class="sp-icon-btn" :aria-label="`Edit ${x.description}`" title="Edit" @click="openForm(x)"><span v-html="ICONS.edit"></span></button>
              <button v-if="perms.delete" type="button" class="sp-icon-btn" :aria-label="`Delete ${x.description}`" title="Delete" @click="deleting = x"><span v-html="ICONS.trash"></span></button>
            </div>
          </article>
        </section>
      </div>
    </section>

    <!-- Record / edit -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="form" class="sp-backdrop" @click="closeForm"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="form" class="sp-modal-wrap">
          <form class="sp-modal wide" role="dialog" aria-modal="true" aria-labelledby="xp-title" novalidate @submit.prevent="save">
            <div class="sp-modal-head">
              <div>
                <h3 id="xp-title">{{ form.id ? "Edit expense" : "Record an expense" }}</h3>
                <p>It's included in the finance dashboard, profit and loss, and reports.</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="closeForm"><span v-html="ICONS.close"></span></button>
            </div>

            <div class="sp-field">
              <span class="sp-label">Category</span>
              <div class="xp-pickgrid">
                <button v-for="c in data?.categories ?? []" :key="c.value" type="button" class="xp-pick" :class="{ on: form.category === c.value }" @click="form.category = c.value">
                  <span :class="`tone-${look(c.value).tone}`" v-html="ICONS[look(c.value).icon]"></span>{{ c.label }}
                </button>
              </div>
              <small v-if="isStockCategory" class="sp-hint">Counted as a stock purchase: its cost reaches profit when the flowers sell, so it isn't counted twice.</small>
              <small v-if="errors.category" class="sp-error">{{ errors.category }}</small>
            </div>

            <div class="sp-grid-2">
              <label class="sp-field sp-span-all">
                <span>What was it for?</span>
                <input v-model="form.description" class="sp-input" :class="{ invalid: errors.description }" maxlength="255" :placeholder="placeholder" />
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
                  <option v-for="m in data?.payment_methods ?? []" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
              </label>
              <label class="sp-field">
                <span>Paid to <small>(optional)</small></span>
                <input v-model="form.payee" class="sp-input" maxlength="120" placeholder="e.g. Dangwa supplier, Meralco" />
              </label>
              <label class="sp-field">
                <span>Reference <small>(optional)</small></span>
                <input v-model="form.reference" class="sp-input" maxlength="80" placeholder="OR or invoice number" />
              </label>
              <label class="sp-field">
                <span>Notes <small>(optional)</small></span>
                <input v-model="form.notes" class="sp-input" maxlength="1000" />
              </label>
            </div>

            <!-- Receipts and documents -->
            <div class="sp-field">
              <span class="sp-label">Receipts and documents <small>({{ fileCount }} of {{ maxFiles }} · photos or PDFs, up to 10 MB each)</small></span>
              <ul v-if="form.existing.length || form.added.length" class="xp-attach">
                <li v-for="f in form.existing" :key="`e${f.id}`" :class="{ removed: form.remove.includes(f.id) }">
                  <span v-html="ICONS[f.image ? 'image' : 'note']"></span>
                  <span class="name">{{ f.name }}</span>
                  <small>{{ size(f.size) }}</small>
                  <button type="button" class="sp-icon-btn" :aria-label="form.remove.includes(f.id) ? `Keep ${f.name}` : `Remove ${f.name}`" @click="toggleRemove(f.id)">
                    <span v-html="ICONS[form.remove.includes(f.id) ? 'restore' : 'trash']"></span>
                  </button>
                </li>
                <li v-for="(f, i) in form.added" :key="`a${i}`" class="new">
                  <span v-html="ICONS[f.type.startsWith('image/') ? 'image' : 'note']"></span>
                  <span class="name">{{ f.name }}</span>
                  <small>{{ size(f.size) }} · new</small>
                  <button type="button" class="sp-icon-btn" :aria-label="`Remove ${f.name}`" @click="form.added.splice(i, 1)"><span v-html="ICONS.close"></span></button>
                </li>
              </ul>
              <label v-if="fileCount < maxFiles" class="xp-drop" :class="{ over: dragOver }" @dragover.prevent="dragOver = true" @dragleave.prevent="dragOver = false" @drop.prevent="onDrop">
                <input type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" @change="onPick" />
                <span class="xp-drop-ic" v-html="ICONS.upload"></span>
                <span><b>Add receipts or documents</b><small>Drop them here or choose files</small></span>
              </label>
              <small v-if="errors.files" class="sp-error">{{ errors.files }}</small>
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
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="xpd-title">
            <div class="sp-modal-head">
              <div>
                <h3 id="xpd-title">Delete this expense?</h3>
                <p>{{ deleting.description }} · {{ money(deleting.amount) }} on {{ formatDate(deleting.spent_on) }}</p>
              </div>
            </div>
            <p class="sp-hint">It's removed from the books along with its receipts and documents. This can't be undone.</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="deleting = null">Cancel</button>
              <button type="button" class="sp-btn sp-btn-danger" :disabled="saving" @click="remove">{{ saving ? "Deleting…" : "Delete" }}</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref, watch } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import HrPage from "../../../components/hr/HrPage.vue";
import RangePicker from "../../../components/vendor/finance/RangePicker.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { change, financeRange, formatDate, money, ymd } from "../../../components/vendor/finance/financeUi";
import { useAuth } from "../../../composables/useAuth";
import { useAssignment } from "../../../composables/useAssignment";
import "../../../components/vendor/finance/finance.css";

// How each category looks
const LOOK = {
  flowers: { icon: "flower", tone: "pink", color: "#f687b3" },
  supplies: { icon: "box", tone: "blue", color: "#63b3ed" },
  packaging: { icon: "cube", tone: "violet", color: "#b794f4" },
  delivery: { icon: "truck", tone: "amber", color: "#f6ad55" },
  utilities: { icon: "sparkle", tone: "teal", color: "#4fd1c5" },
  rent: { icon: "store", tone: "green", color: "#48bb78" },
  marketing: { icon: "megaphone", tone: "red", color: "#fc8181" },
  equipment: { icon: "gear", tone: "slate", color: "#a0aec0" },
  maintenance: { icon: "edit", tone: "slate", color: "#a0aec0" },
  fees: { icon: "card", tone: "slate", color: "#a0aec0" },
  taxes: { icon: "receipt", tone: "slate", color: "#a0aec0" },
  other: { icon: "note", tone: "slate", color: "#a0aec0" },
};
const look = (c) => LOOK[c] ?? LOOK.other;
const PLACEHOLDER = {
  flowers: "e.g. 10 dozen red roses from Dangwa",
  supplies: "e.g. floral foam and wire",
  packaging: "e.g. kraft paper, ribbons and boxes",
  delivery: "e.g. fuel or courier fee",
  utilities: "e.g. October electricity bill",
  rent: "e.g. October stall rent",
};

const { user } = useAuth();
const { can } = useAssignment();
const isEmployee = computed(() => user.value?.type === "employee");
// Finance staff use the ERP routes (they check the Expenses permission); the shop owner uses the vendor ones
const base = computed(() => (isEmployee.value ? "finance/expenses" : "vendor/books/expenses"));
const perms = computed(() => ({
  create: !isEmployee.value || can("expenses", "create"),
  edit: !isEmployee.value || can("expenses", "edit"),
  delete: !isEmployee.value || can("expenses", "delete"),
}));
const today = ymd(new Date());

const data = ref(null);
const error = ref("");
const loading = ref(false);
const category = ref("");
const search = ref("");
const onlyMissing = ref(false);

const form = ref(null);
const errors = ref({});
const formError = ref("");
const saving = ref(false);
const deleting = ref(null);
const dragOver = ref(false);

// ▲/▼ against the previous period; spending going up is bad
const Trend = defineComponent({
  props: { now: Number, before: Number, cost: Boolean },
  setup: (p) => () => {
    const c = change(p.now ?? 0, p.before ?? 0);
    if (c === null) return h("span", { class: "fx-trend flat" }, p.now ? "New" : "—");
    const good = p.cost ? c <= 0 : c >= 0;
    return h("span", { class: ["fx-trend", c === 0 ? "flat" : good ? "up" : "down"] }, `${c > 0 ? "▲" : c < 0 ? "▼" : "•"} ${Math.abs(c)}%`);
  },
});

const countText = (n, word) => `${n} ${word}${n === 1 ? "" : "s"}`;
const share = (v) => (data.value?.total > 0 ? Math.round((v / data.value.total) * 100) : 0);
const size = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round((b || 0) / 1024))} KB`);
const maxFiles = computed(() => data.value?.max_files ?? 5);

const kpis = computed(() => {
  const d = data.value;
  const operating = d.total - d.stock_total;
  const count = d.by_category.reduce((s, c) => s + c.count, 0);
  return [
    { key: "total", label: "Total recorded", icon: "receipt", tone: "amber", value: money(d.total), trend: true, sub: countText(count, "expense") },
    { key: "operating", label: "Running costs", icon: "store", tone: "teal", value: money(operating), sub: "everything except stock" },
    { key: "stock", label: "Flowers bought", icon: "flower", tone: "pink", value: money(d.stock_total), sub: "counted as stock" },
    { key: "missing", label: "Missing a receipt", icon: "image", tone: d.without_files ? "red" : "green", value: String(d.without_files), hero: !d.without_files, sub: d.without_files ? "attach them to keep the books tidy" : "every expense has one" },
  ];
});

const filtered = computed(() => !!(category.value || search.value.trim() || onlyMissing.value));
const shown = computed(() => (data.value?.expenses ?? []).filter((x) => !onlyMissing.value || (!x.files.length && !x.receipt_url)));
const days = computed(() => {
  const map = new Map();
  for (const x of shown.value) {
    if (!map.has(x.spent_on)) map.set(x.spent_on, { date: x.spent_on, label: new Date(`${x.spent_on}T00:00:00`).toLocaleDateString("en-PH", { weekday: "long", month: "long", day: "numeric" }), total: 0, items: [] });
    const d = map.get(x.spent_on);
    d.items.push(x);
    d.total += x.amount;
  }
  return [...map.values()];
});

const fileCount = computed(() => (form.value ? form.value.existing.filter((f) => !form.value.remove.includes(f.id)).length + form.value.added.length : 0));
const isStockCategory = computed(() => (data.value?.stock_categories ?? []).includes(form.value?.category));
const placeholder = computed(() => PLACEHOLDER[form.value?.category] ?? "What did you pay for?");

async function load() {
  error.value = "";
  loading.value = true;
  try {
    const res = await api.get(base.value, { params: { from: financeRange.from, to: financeRange.to, category: category.value || undefined, search: search.value.trim() || undefined } });
    data.value = res.data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load expenses for this period.");
  } finally {
    loading.value = false;
  }
}

function setCategory(c) {
  category.value = c;
  load();
}

function openForm(x = null) {
  errors.value = {};
  formError.value = "";
  form.value = x
    ? { id: x.id, category: x.category, description: x.description, amount: x.amount, spent_on: x.spent_on, payment_method: x.payment_method ?? "", payee: x.payee ?? "", reference: x.reference ?? "", notes: x.notes ?? "", existing: x.files, remove: [], added: [] }
    : { id: null, category: category.value || "flowers", description: "", amount: null, spent_on: today, payment_method: "", payee: "", reference: "", notes: "", existing: [], remove: [], added: [] };
}

function closeForm() {
  form.value = null;
}

function addFiles(list) {
  errors.value.files = "";
  for (const f of list) {
    if (fileCount.value >= maxFiles.value) {
      errors.value.files = `Up to ${maxFiles.value} files per expense.`;
      break;
    }
    if (!/^image\/(jpeg|png|webp)$|^application\/pdf$/.test(f.type)) {
      errors.value.files = "Attach photos (JPG, PNG, WebP) or PDFs.";
      continue;
    }
    if (f.size > 10 * 1048576) {
      errors.value.files = `${f.name} is over 10 MB.`;
      continue;
    }
    form.value.added.push(f);
  }
}
const onPick = (e) => {
  addFiles([...e.target.files]);
  e.target.value = "";
};
const onDrop = (e) => {
  dragOver.value = false;
  addFiles([...e.dataTransfer.files]);
};
function toggleRemove(id) {
  const r = form.value.remove;
  form.value.remove = r.includes(id) ? r.filter((x) => x !== id) : [...r, id];
}

function validate() {
  const f = form.value;
  const e = {};
  if (!f.category) e.category = "Pick a category.";
  if (!f.description || f.description.trim().length < 2) e.description = "Say what it was for.";
  if (!(Number(f.amount) > 0)) e.amount = "Enter the amount.";
  if (!f.spent_on) e.spent_on = "Pick the date.";
  else if (f.spent_on > today) e.spent_on = "The date can't be in the future.";
  errors.value = e;
  return !Object.keys(e).length;
}

async function save() {
  if (!validate()) return;
  saving.value = true;
  formError.value = "";
  const f = form.value;
  const body = new FormData();
  for (const k of ["category", "description", "amount", "spent_on", "payment_method", "payee", "reference", "notes"]) {
    const v = typeof f[k] === "string" ? f[k].trim() : f[k];
    if (v !== "" && v !== null && v !== undefined) body.append(k, v);
  }
  f.added.forEach((file) => body.append("files[]", file));
  f.remove.forEach((id) => body.append("remove_files[]", id));
  try {
    const res = await api.post(f.id ? `${base.value}/${f.id}` : base.value, body);
    toast.success(res.data.message);
    form.value = null;
    load();
  } catch (e) {
    const fieldErrors = e?.response?.data?.errors;
    if (fieldErrors) {
      errors.value = Object.fromEntries(Object.entries(fieldErrors).map(([k, v]) => [k.startsWith("files") ? "files" : k, v[0]]));
    }
    formError.value = fieldErrors ? "" : apiMessage(e, "Couldn't save the expense.");
    // The expense may be saved even when a file didn't upload
    if (e?.response?.status === 502) load();
  } finally {
    saving.value = false;
  }
}

async function remove() {
  saving.value = true;
  try {
    await api.delete(`${base.value}/${deleting.value.id}`);
    toast.success("Expense deleted.");
    deleting.value = null;
    load();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't delete the expense."));
  } finally {
    saving.value = false;
  }
}

// Files are private: ask for a short-lived link, then open it
async function openFile(x, f) {
  const tab = window.open("", "_blank");
  try {
    const res = await api.get(`${base.value}/${x.id}/files/${f.id}/link`);
    if (tab) tab.location.href = res.data.data.url;
    else window.location.href = res.data.data.url;
  } catch (e) {
    tab?.close();
    toast.error(apiMessage(e, "Couldn't open the file."));
  }
}

let searchTimer = null;
watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(load, 300);
});

onMounted(load);
</script>

<style scoped>
.fx-kpi.hero {
  background: linear-gradient(150deg, var(--bc-accent-soft) 0%, var(--v-surface) 60%);
  border-color: var(--bc-accent-muted);
}
.fx-kpi-sub {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
}
.xp-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.xp-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 11px;
}

.xp-two {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 320px;
  gap: 16px;
  align-items: start;
  margin-bottom: 18px;
}

/* Categories */
.xp-cats {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}
.xp-cat {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-surface);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, opacity 0.15s, transform 0.15s;
}
.xp-cat:hover {
  border-color: var(--bc-accent);
  transform: translateY(-1px);
}
.xp-cat.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.xp-cat.dim {
  opacity: 0.5;
}
.xp-cat-ic {
  flex: none;
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
}
.xp-cat-ic :deep(svg) {
  width: 16px;
  height: 16px;
}
.xp-cat-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.xp-cat-top {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--v-text);
}
.xp-cat-top b {
  font-weight: 600;
}
.xp-cat-top em {
  margin-left: auto;
  font-style: normal;
  font-weight: 600;
}
.xp-cat-top .sp-chip {
  padding: 1px 7px;
  font-size: 10.5px;
}
.xp-bar {
  height: 6px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.xp-bar span {
  display: block;
  height: 100%;
  border-radius: 999px;
}
.xp-cat small {
  font-size: 11.5px;
  color: var(--v-muted);
}

/* Automatic */
.xp-auto dl {
  margin: 0 0 12px;
  display: grid;
  gap: 10px;
}
.xp-auto dl div {
  display: flex;
  justify-content: space-between;
  gap: 10px;
}
.xp-auto dt {
  display: flex;
  flex-direction: column;
  font-size: 13px;
  color: var(--v-text);
}
.xp-auto dt small {
  font-size: 11.5px;
  color: var(--v-faint);
}
.xp-auto dd {
  margin: 0;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  white-space: nowrap;
}
.xp-auto .sp-hint {
  margin: 0;
  padding-top: 12px;
  border-top: 1px solid var(--v-border);
}

/* List */
.xp-list {
  min-width: 0;
}
.xp-toolbar .sp-select {
  width: auto;
  flex: none;
}
.xp-only {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-size: 12.5px;
  color: var(--v-text-2);
  cursor: pointer;
  white-space: nowrap;
}
.xp-only input {
  width: 15px;
  height: 15px;
  accent-color: var(--bc-accent);
}
.xp-days {
  display: grid;
  gap: 14px;
  transition: opacity 0.2s;
}
.xp-days.dim {
  opacity: 0.55;
}
.xp-day-head {
  position: sticky;
  top: 0;
  z-index: 1;
  display: flex;
  justify-content: space-between;
  gap: 10px;
  padding: 7px 10px;
  border-radius: 9px;
  background: var(--v-subtle);
  font-size: 12px;
  font-weight: 600;
  color: var(--v-text-2);
}
.xp-day-head b {
  color: var(--v-text);
}
.xp-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 6px;
  border-bottom: 1px solid var(--v-border);
}
.xp-day .xp-row:last-child {
  border-bottom: 0;
}
.xp-row .grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.xp-row strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.xp-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--v-muted);
}
.xp-meta .sp-chip {
  padding: 1px 8px;
  font-size: 10.5px;
}
.xp-notes {
  font-size: 12px;
  color: var(--v-text-2);
}
.xp-files {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.xp-file {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  max-width: 220px;
  padding: 3px 9px;
  border: 1px solid var(--v-border-strong);
  border-radius: 999px;
  background: var(--v-surface);
  font-family: inherit;
  font-size: 11.5px;
  color: var(--v-text-2);
  text-decoration: none;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: pointer;
  transition: border-color 0.15s, color 0.15s;
}
.xp-file:hover {
  border-color: var(--bc-accent);
  color: var(--bc-accent-strong);
}
.xp-file :deep(svg) {
  flex: none;
  width: 12px;
  height: 12px;
}
.xp-nofile {
  font-size: 11.5px;
  color: var(--v-red-text);
}
.xp-amt {
  flex: none;
  min-width: 110px;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}
.xp-amt strong {
  font-size: 14px;
}
.xp-amt small {
  font-size: 11.5px;
  color: var(--v-faint);
  white-space: nowrap;
}
.xp-actions {
  flex: none;
  display: flex;
  gap: 4px;
}

/* Form */
.sp-modal > .sp-field {
  margin-bottom: 14px;
}
.xp-pickgrid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 6px;
}
.xp-pick {
  display: flex;
  align-items: center;
  gap: 7px;
  padding: 8px 10px;
  border: 1px solid var(--v-border-strong);
  border-radius: 10px;
  background: var(--v-surface);
  font-family: inherit;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--v-text-2);
  text-align: left;
  cursor: pointer;
  transition: all 0.15s;
}
.xp-pick > span {
  flex: none;
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border-radius: 6px;
}
.xp-pick :deep(svg) {
  width: 13px;
  height: 13px;
}
.xp-pick:hover {
  border-color: var(--bc-accent-border);
}
.xp-pick.on {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
  color: var(--bc-accent-strong);
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
.xp-attach {
  list-style: none;
  margin: 0 0 8px;
  padding: 0;
  display: grid;
  gap: 6px;
}
.xp-attach li {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 6px 6px 6px 12px;
  border: 1px solid var(--v-border);
  border-radius: 10px;
  background: var(--v-subtle);
  font-size: 12.5px;
  color: var(--v-text);
}
.xp-attach li.new {
  border-color: var(--bc-accent-muted);
  background: var(--bc-accent-soft);
}
.xp-attach li.removed {
  opacity: 0.5;
}
.xp-attach li.removed .name {
  text-decoration: line-through;
}
.xp-attach li > span:first-child :deep(svg) {
  width: 15px;
  height: 15px;
  color: var(--v-muted);
}
.xp-attach .name {
  flex: 1;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.xp-attach small {
  font-size: 11.5px;
  color: var(--v-faint);
  white-space: nowrap;
}
.xp-drop {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border: 1.5px dashed var(--v-border-strong);
  border-radius: 12px;
  background: var(--v-subtle);
  cursor: pointer;
  transition: border-color 0.15s, background 0.15s;
}
.xp-drop:hover,
.xp-drop.over {
  border-color: var(--bc-accent);
  background: var(--bc-accent-soft);
}
.xp-drop input {
  display: none;
}
.xp-drop-ic {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: var(--v-surface);
  color: var(--bc-accent-strong);
}
.xp-drop-ic :deep(svg) {
  width: 17px;
  height: 17px;
}
.xp-drop > span:last-child {
  display: flex;
  flex-direction: column;
  font-size: 13px;
  color: var(--v-text);
}
.xp-drop small {
  font-size: 11.5px;
  color: var(--v-muted);
}

@media (max-width: 1100px) {
  .xp-two {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 720px) {
  .xp-cats {
    grid-template-columns: minmax(0, 1fr);
  }
  .xp-pickgrid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .xp-row {
    flex-wrap: wrap;
  }
  .xp-row .grow {
    flex-basis: calc(100% - 60px);
  }
  .xp-amt {
    margin-left: 56px;
    align-items: flex-start;
  }
  .xp-actions {
    margin-left: auto;
  }
}
</style>
