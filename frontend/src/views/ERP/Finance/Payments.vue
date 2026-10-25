<template>
  <HrPage title="Payments" subtitle="PayMongo and cash-on-delivery payments, what they cost in fees, and whether they match the orders and the books.">
    <template #actions>
      <RangePicker @change="load(1)" />
      <button type="button" class="sp-btn sp-btn-ghost" :disabled="loading" @click="refresh()">Refresh</button>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="refresh()">Try again</button>
    </div>

    <!-- Headline numbers -->
    <section class="fx-kpis" aria-label="Payments this period">
      <template v-if="!data">
        <div v-for="n in 4" :key="n" class="sp-card fx-kpi"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton fx-sk-big"></div><div class="bc-skeleton sp-sk-line w50"></div></div>
      </template>
      <template v-else>
        <article v-for="k in kpis" :key="k.key" class="sp-card fx-kpi pm-kpi" :class="{ hero: k.hero }">
          <span class="fx-kpi-label"><span class="pm-ic" :class="`tone-${k.tone}`" v-html="ICONS[k.icon]"></span>{{ k.label }}</span>
          <strong>{{ k.value }}</strong>
          <span class="fx-kpi-sub">{{ k.sub }}</span>
        </article>
      </template>
    </section>

    <div class="pm-two">
      <!-- PayMongo -->
      <section class="sp-card" aria-label="PayMongo">
        <div class="sp-card-head">
          <div><h2>PayMongo</h2><p>E-wallet and card payments</p></div>
          <span v-if="data && pm.success_rate !== null" class="pm-rate" :class="rateTone">{{ pm.success_rate }}% successful</span>
        </div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <template v-else>
          <div class="pm-statuses">
            <button v-for="s in PM_STATUS_TILES" :key="s" type="button" class="pm-status" :class="STATUS[s].tone" @click="openList({ tab: 'all', method: 'paymongo', status: s })">
              <span><i></i>{{ STATUS[s].label }}</span>
              <strong>{{ pm.statuses[s].count }}</strong>
              <small>{{ money(pm.statuses[s].amount) }}</small>
            </button>
          </div>
          <div class="pm-fees">
            <div><span>Processing fees</span><b>{{ money(pm.fees) }}</b></div>
            <div><span>Net received</span><b>{{ money(pm.net_received) }}</b></div>
          </div>
          <p v-if="pm.paid_count && pm.fees_known < pm.paid_count" class="sp-hint pm-hint">
            PayMongo reported the fee for {{ pm.fees_known }} of {{ pm.paid_count }} paid payments. The rest count at their full amount until PayMongo's details are on file.
          </p>
          <ul v-if="pm.providers.length" class="pm-providers">
            <li v-for="p in pm.providers" :key="p.provider">
              <span class="pm-provider">{{ PROVIDER[p.provider] ?? p.provider }}</span>
              <span class="muted">{{ countText(p.count, "payment") }}<template v-if="p.fees"> · {{ money(p.fees) }} fees</template></span>
              <b>{{ money(p.amount) }}</b>
            </li>
          </ul>
        </template>
      </section>

      <!-- Cash on delivery -->
      <section class="sp-card" aria-label="Cash on delivery">
        <div class="sp-card-head">
          <div><h2>Cash on delivery</h2><p>From the doorstep to the bank</p></div>
        </div>
        <div v-if="!data" class="bc-skeleton sp-sk-block"></div>
        <template v-else>
          <ol class="pm-pipeline">
            <li v-for="(s, i) in COD_PIPELINE" :key="s" :class="[STAGE[s].tone, { on: tab === 'cod' && codStage === s }]">
              <button type="button" @click="openList({ tab: 'cod', codStage: s })">
                <span class="pm-step">{{ i + 1 }}</span>
                <span class="pm-step-label">{{ STAGE[s].label }}</span>
                <strong>{{ cod.stages[s].count }}</strong>
                <small>{{ money(cod.stages[s].amount) }}</small>
              </button>
            </li>
          </ol>
          <div class="pm-fees">
            <div><span>Cash received</span><b>{{ money(cod.collected) }}</b></div>
            <div :class="{ warn: cod.short > 0 }"><span>Short</span><b>{{ money(cod.short) }}</b></div>
          </div>
          <p v-if="!data.cod_ready" class="sp-hint pm-hint">Cash records are still being set up. Recording will be available in a few minutes.</p>
          <p v-else class="sp-hint pm-hint">Record the cash when the rider hands it over, then settle it once it's deposited or counted into the books. Settle several at once from the Cash on delivery list.</p>
        </template>
      </section>
    </div>

    <!-- Reconciliation -->
    <section class="sp-card pm-rec" aria-label="Reconciliation">
      <div class="sp-card-head">
        <div><h2>Reconciliation</h2><p>Each paid order checked against PayMongo, the books and the cash received</p></div>
        <button v-if="data && rec.issue_count" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="openList({ tab: 'issues' })">Review {{ rec.issue_count }}</button>
      </div>
      <div v-if="!data" class="bc-skeleton sp-sk-line w50"></div>
      <template v-else>
        <div class="pm-rec-bar">
          <div class="pm-meter" role="img" :aria-label="`${rec.matched} of ${rec.checked} match`"><span :style="{ width: `${matchPct}%` }"></span></div>
          <p><b>{{ rec.matched }}</b> of {{ rec.checked }} payments match<template v-if="rec.issue_count"> · <span class="warn">{{ countText(rec.issue_count, "needs", "need") }} a look</span></template><template v-else-if="rec.checked"> · everything lines up</template></p>
        </div>
        <ul v-if="rec.issues.length" class="pm-issues">
          <li v-for="r in rec.issues.slice(0, 4)" :key="r.id">
            <span class="pm-issue-ic" v-html="ICONS.alert"></span>
            <div class="grow">
              <strong>{{ r.order_number }} <span class="muted">· {{ r.customer }}</span></strong>
              <span v-for="i in r.issues" :key="i.code" class="pm-issue-msg">{{ i.message }}</span>
            </div>
            <b>{{ money(r.amount) }}</b>
          </li>
        </ul>
      </template>
    </section>

    <!-- Lists -->
    <section class="sp-card pm-list" aria-label="Payments list">
      <div class="pm-list-head">
        <div class="sp-seg" role="tablist" aria-label="Show">
          <button v-for="t in TABS" :key="t.key" type="button" role="tab" :aria-selected="tab === t.key" :class="{ on: tab === t.key }" @click="openList({ tab: t.key })">
            {{ t.label }}<span v-if="t.key === 'issues' && rec.issue_count" class="count">{{ rec.issue_count }}</span>
          </button>
        </div>
        <button v-if="tab === 'cod' && canRecord && selected.length" type="button" class="sp-btn sp-btn-primary sp-btn-sm" @click="openSettle">Settle {{ selected.length }} · {{ money(selectedAmount) }}</button>
      </div>

      <div class="sp-toolbar pm-toolbar">
        <template v-if="tab === 'all'">
          <select v-model="method" class="sp-select" aria-label="Payment method" @change="load(1)">
            <option value="">PayMongo and cash</option>
            <option value="paymongo">PayMongo</option>
            <option value="cod">Cash on delivery</option>
          </select>
          <select v-model="status" class="sp-select" aria-label="Status" @change="load(1)">
            <option value="">Any status</option>
            <option v-for="(s, key) in STATUS" :key="key" :value="key">{{ s.label }}</option>
          </select>
        </template>
        <div v-else-if="tab === 'cod'" class="pm-chips" role="group" aria-label="Cash stage">
          <button type="button" class="sp-pick" :class="{ on: !codStage }" @click="setStage('')">Every stage</button>
          <button v-for="s in COD_PIPELINE" :key="s" type="button" class="sp-pick" :class="{ on: codStage === s }" @click="setStage(s)">{{ STAGE[s].label }}</button>
        </div>
        <label class="sp-search">
          <span v-html="ICONS.search"></span>
          <input v-model="search" class="sp-input" type="search" placeholder="Order, customer or reference" aria-label="Search payments" />
        </label>
      </div>

      <ul v-if="!data" class="pm-rows">
        <li v-for="n in 5" :key="n" class="pm-row"><div class="bc-skeleton fx-icon"></div><div class="grow"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-line w30"></div></div></li>
      </ul>
      <div v-else-if="!data.rows.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS[tab === 'issues' ? 'check' : 'card']"></span>
        <h3>{{ emptyTitle }}</h3>
        <p>{{ emptyText }}</p>
      </div>
      <template v-else>
        <ul class="pm-rows" :class="{ dim: loading }">
          <li v-for="r in list.rows.value" :key="r.id" class="pm-row" :class="{ picked: selected.includes(r.id) }">
            <label v-if="tab === 'cod' && canRecord" class="pm-check" :class="{ off: r.cod?.stage !== 'collected' }">
              <input type="checkbox" :disabled="r.cod?.stage !== 'collected'" :checked="selected.includes(r.id)" :aria-label="`Select ${r.order_number} to settle`" @change="toggle(r)" />
            </label>
            <span class="fx-icon" :class="`tone-${tab === 'cod' ? STAGE[r.cod?.stage]?.tone : STATUS[r.status].tone}`" v-html="ICONS[r.method === 'cod' ? 'wallet' : 'card']"></span>
            <div class="grow">
              <strong>{{ r.order_number }} <span class="muted">· {{ r.customer }}</span></strong>
              <span class="pm-sub">
                {{ PROVIDER[r.provider] ?? r.provider }}<template v-if="r.reference"> · <span class="mono">{{ r.reference }}</span></template> · placed {{ formatDate(r.placed_at, true) }}
              </span>
              <span v-if="r.method === 'cod' && r.cod?.collected_amount !== null" class="pm-sub strong-sub">
                Received {{ money(r.cod.collected_amount) }}<template v-if="r.cod.collected_by"> from {{ r.cod.collected_by }}</template> on {{ formatDate(r.cod.collected_on) }}<template v-if="r.cod.settled_on"> · settled {{ formatDate(r.cod.settled_on) }}<template v-if="r.cod.settlement_reference"> ({{ r.cod.settlement_reference }})</template></template>
              </span>
              <span v-for="i in r.issues" :key="i.code" class="pm-issue-msg">{{ i.message }}</span>
            </div>
            <div class="pm-chips-col">
              <span class="sp-chip" :class="STATUS[r.status].tone">{{ STATUS[r.status].label }}</span>
              <span v-if="r.method === 'cod' && r.cod" class="sp-chip" :class="STAGE[r.cod.stage].tone">{{ STAGE[r.cod.stage].label }}</span>
            </div>
            <div class="pm-amt">
              <strong>{{ money(r.amount) }}</strong>
              <small v-if="r.fee !== null">−{{ money(r.fee) }} fee · {{ money(r.net) }} net</small>
              <small v-if="r.cod?.difference" :class="r.cod.difference < 0 ? 'neg' : 'pos'">{{ r.cod.difference < 0 ? "Short" : "Over" }} {{ money(Math.abs(r.cod.difference)) }}</small>
            </div>
            <div v-if="canRecord && data.cod_ready" class="pm-actions">
              <template v-if="r.method !== 'cod'"></template>
              <button v-else-if="['awaiting_delivery', 'to_collect'].includes(r.cod?.stage)" type="button" class="sp-btn sp-btn-sm" :class="r.cod.stage === 'to_collect' ? 'sp-btn-primary' : 'sp-btn-ghost'" @click="openCollect(r)">Record cash</button>
              <template v-else-if="r.cod?.stage === 'collected'">
                <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="openCollect(r)">Edit</button>
                <button type="button" class="sp-icon-btn" :aria-label="`Undo cash for ${r.order_number}`" title="Undo" @click="undoTarget = r"><span v-html="ICONS.restore"></span></button>
              </template>
              <button v-else-if="r.cod?.stage === 'settled' || (r.cod?.stage === 'void' && r.cod?.collected_amount !== null)" type="button" class="sp-icon-btn" :aria-label="`Undo for ${r.order_number}`" title="Undo" @click="undoTarget = r"><span v-html="ICONS.restore"></span></button>
            </div>
          </li>
        </ul>
        <ShowMore v-if="data && data.rows.length" tone="vendor" :has-more="list.hasMore.value" :loading="list.loadingMore.value" :shown="list.rows.value.length" :total="list.total.value" @more="list.showMore()" />
      </template>
    </section>

    <!-- Record cash -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="collectForm" class="sp-backdrop" @click="collectForm = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="collectForm" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="pm-collect-title" novalidate @submit.prevent="saveCollect">
            <div class="sp-modal-head">
              <div>
                <h3 id="pm-collect-title">Record cash received</h3>
                <p>{{ collectForm.order_number }} · {{ collectForm.customer }} · order total {{ money(collectForm.total) }}</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="collectForm = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span>Amount received</span>
                <div class="money-in"><i>₱</i><input v-model.number="collectForm.amount" type="number" min="0" step="0.01" class="sp-input" placeholder="0.00" /></div>
                <small v-if="collectDiff" class="sp-hint" :class="collectDiff < 0 ? 'neg' : ''">{{ collectDiff < 0 ? "Short by" : "Over by" }} {{ money(Math.abs(collectDiff)) }}</small>
              </label>
              <label class="sp-field">
                <span>Date received</span>
                <input v-model="collectForm.collected_on" type="date" class="sp-input" :max="today" />
              </label>
              <label class="sp-field sp-span-all">
                <span>Handed over by <small>(optional)</small></span>
                <input v-model="collectForm.collected_by" class="sp-input" maxlength="120" placeholder="e.g. rider's name" />
              </label>
              <label class="sp-field sp-span-all">
                <span>Notes <small>(optional)</small></span>
                <input v-model="collectForm.notes" class="sp-input" maxlength="500" placeholder="e.g. customer paid ₱50 less, agreed by phone" />
              </label>
            </div>
            <p v-if="formError" class="sp-error">{{ formError }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="collectForm = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="saving">{{ saving ? "Saving…" : "Record cash" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Settle -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="settleForm" class="sp-backdrop" @click="settleForm = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="settleForm" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="pm-settle-title" novalidate @submit.prevent="saveSettle">
            <div class="sp-modal-head">
              <div>
                <h3 id="pm-settle-title">Settle {{ countText(selected.length, "collection") }}</h3>
                <p>{{ money(selectedAmount) }} of cash, deposited or counted into the books</p>
              </div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="settleForm = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span>Settled on</span>
                <input v-model="settleForm.settled_on" type="date" class="sp-input" :max="today" />
              </label>
              <label class="sp-field">
                <span>Reference <small>(optional)</small></span>
                <input v-model="settleForm.reference" class="sp-input" maxlength="80" placeholder="Deposit slip or transfer no." />
              </label>
              <label class="sp-field sp-span-all">
                <span>Notes <small>(optional)</small></span>
                <input v-model="settleForm.notes" class="sp-input" maxlength="500" />
              </label>
            </div>
            <p v-if="formError" class="sp-error">{{ formError }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="settleForm = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="saving">{{ saving ? "Saving…" : "Mark as settled" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Undo -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="undoTarget" class="sp-backdrop" @click="undoTarget = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="undoTarget" class="sp-modal-wrap">
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="pm-undo-title">
            <div class="sp-modal-head">
              <div>
                <h3 id="pm-undo-title">{{ undoTarget.cod.settled_on ? "Undo the settlement?" : "Remove the cash received?" }}</h3>
                <p>{{ undoTarget.order_number }} · {{ money(undoTarget.cod.collected_amount) }}</p>
              </div>
            </div>
            <p class="sp-hint">{{ undoTarget.cod.settled_on ? "The cash goes back to received, so it can be settled again." : "The order goes back to cash still to collect." }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="undoTarget = null">Cancel</button>
              <button type="button" class="sp-btn sp-btn-danger" :disabled="saving" @click="saveUndo">{{ saving ? "Undoing…" : "Undo" }}</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { toast } from "@/utils/snackbar";
import api from "../../../plugins/axios";
import ShowMore from "../../../components/common/ShowMore.vue";
import { useShowMoreInto } from "../../../composables/useShowMore";
import HrPage from "../../../components/hr/HrPage.vue";
import RangePicker from "../../../components/vendor/finance/RangePicker.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { financeRange, formatDate, money, ymd } from "../../../components/vendor/finance/financeUi";
import { useAuth } from "../../../composables/useAuth";
import { useAssignment } from "../../../composables/useAssignment";
import "../../../components/vendor/finance/finance.css";

const STATUS = {
  paid: { label: "Successful", tone: "green" },
  pending: { label: "Pending", tone: "amber" },
  failed: { label: "Failed", tone: "red" },
  expired: { label: "Expired", tone: "slate" },
  refunded: { label: "Refunded", tone: "pink" },
  cancelled: { label: "Voided", tone: "slate" },
};
const STAGE = {
  awaiting_delivery: { label: "Awaiting delivery", tone: "slate" },
  to_collect: { label: "To collect", tone: "amber" },
  collected: { label: "Cash received", tone: "blue" },
  settled: { label: "Settled", tone: "green" },
  void: { label: "Voided", tone: "slate" },
};
const COD_PIPELINE = ["awaiting_delivery", "to_collect", "collected", "settled"];
const PM_STATUS_TILES = ["paid", "pending", "failed", "expired"];
const PROVIDER = { gcash: "GCash", paymaya: "Maya", maya: "Maya", grab_pay: "GrabPay", card: "Card", dob: "Online banking", billease: "BillEase", qrph: "QR Ph", ewallet: "E-wallet", cod: "Cash on delivery" };
const TABS = [
  { key: "all", label: "All payments" },
  { key: "cod", label: "Cash on delivery" },
  { key: "issues", label: "Needs a look" },
];

const { user } = useAuth();
const { can } = useAssignment();
const isEmployee = computed(() => user.value?.type === "employee");
// Finance staff use the ERP routes (they check the Payments permission); the shop owner uses the vendor ones
const base = computed(() => (isEmployee.value ? "finance/payments" : "vendor/books/payments"));
const canRecord = computed(() => !isEmployee.value || can("payments", "edit"));
const today = ymd(new Date());

const data = ref(null);
const error = ref("");
const loading = ref(false);
const page = ref(1);
const tab = ref("all");
const method = ref("");
const status = ref("");
const codStage = ref("");
const search = ref("");
const selected = ref([]);
const selectedRows = ref({});

const collectForm = ref(null);
const settleForm = ref(null);
const undoTarget = ref(null);
const saving = ref(false);
const formError = ref("");

const summary = computed(() => data.value?.summary);
const pm = computed(() => summary.value?.paymongo ?? { statuses: {}, providers: [] });
const cod = computed(() => summary.value?.cod ?? { stages: {} });
const rec = computed(() => data.value?.reconciliation ?? { checked: 0, matched: 0, issue_count: 0, issues: [] });
const matchPct = computed(() => (rec.value.checked ? Math.round((rec.value.matched / rec.value.checked) * 100) : 100));
const rateTone = computed(() => (pm.value.success_rate >= 80 ? "good" : pm.value.success_rate >= 50 ? "ok" : "bad"));

const countText = (n, word, plural) => `${n} ${n === 1 ? word : plural ?? `${word}s`}`;

const kpis = computed(() => {
  const all = summary.value.all;
  const failed = all.failed.count + all.expired.count;
  return [
    { key: "paid", label: "Successful", icon: "check", tone: "green", value: money(all.paid.amount), sub: `${countText(all.paid.count, "payment")} received` },
    { key: "pending", label: "Pending", icon: "clock", tone: "amber", value: money(all.pending.amount), sub: `${pm.value.statuses.pending.count} PayMongo · ${cod.value.statuses.pending.count} cash on delivery` },
    { key: "failed", label: "Failed or expired", icon: "alert", tone: "red", value: String(failed), sub: `${money(all.failed.amount + all.expired.amount)} not paid` },
    { key: "fees", label: "Processing fees", icon: "receipt", tone: "violet", value: money(pm.value.fees), hero: true, sub: `net ${money(pm.value.net_received)} from PayMongo` },
  ];
});

const selectedAmount = computed(() => selected.value.reduce((s, id) => s + (selectedRows.value[id]?.cod?.collected_amount ?? 0), 0));
const collectDiff = computed(() => (collectForm.value ? Math.round(((Number(collectForm.value.amount) || 0) - collectForm.value.total) * 100) / 100 : 0));

const emptyTitle = computed(() => {
  if (tab.value === "issues") return "Everything matches";
  if (search.value || method.value || status.value || codStage.value) return "No payments match";
  return "No payments in this period";
});
const emptyText = computed(() => {
  if (tab.value === "issues") return "Every paid order lines up with PayMongo, the books and the cash received.";
  if (search.value || method.value || status.value || codStage.value) return "Try another filter.";
  return "Payments appear here as customers place orders.";
});

// 10 payments first, then 10 more, then 20 at a time; picked rows stay picked
const list = useShowMoreInto(data, "rows", (p) => fetchPayments(p), (payload) => {
  for (const r of payload.rows ?? []) if (selected.value.includes(r.id)) selectedRows.value[r.id] = r;
});
/** Reloads after a change, keeping as many payments showing */
const refresh = () => list.refresh().catch((e) => (error.value = apiMessage(e, "Couldn't load payments for this period.")));
async function load() {
  error.value = "";
  loading.value = true;
  try {
    await list.load();
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load payments for this period.");
  } finally {
    loading.value = false;
  }
}
async function fetchPayments(p) {
  {
    const params = {
      from: financeRange.from,
      to: financeRange.to,
      page: p,
      search: search.value.trim() || undefined,
      method: tab.value === "cod" ? "cod" : tab.value === "all" ? method.value || undefined : undefined,
      status: tab.value === "all" ? status.value || undefined : undefined,
      cod_stage: tab.value === "cod" ? codStage.value || undefined : undefined,
      issues: tab.value === "issues" ? 1 : undefined,
    };
    return (await api.get(base.value, { params })).data.data;
  }
}

// Tiles and tabs open the list with their filter
function openList({ tab: t, method: m = "", status: s = "", codStage: c = "" }) {
  tab.value = t;
  method.value = m;
  status.value = s;
  codStage.value = c;
  if (t !== "cod") clearSelection();
  load(1);
}

function setStage(s) {
  codStage.value = s;
  load(1);
}

function toggle(r) {
  if (selected.value.includes(r.id)) {
    selected.value = selected.value.filter((id) => id !== r.id);
    delete selectedRows.value[r.id];
  } else {
    selected.value = [...selected.value, r.id];
    selectedRows.value[r.id] = r;
  }
}

function clearSelection() {
  selected.value = [];
  selectedRows.value = {};
}

function openCollect(r) {
  formError.value = "";
  collectForm.value = {
    id: r.id,
    order_number: r.order_number,
    customer: r.customer,
    total: r.amount,
    amount: r.cod?.collected_amount ?? r.amount,
    collected_on: r.cod?.collected_on ?? today,
    collected_by: r.cod?.collected_by ?? "",
    notes: r.cod?.notes ?? "",
  };
}

function openSettle() {
  formError.value = "";
  settleForm.value = { settled_on: today, reference: "", notes: "" };
}

async function saveCollect() {
  saving.value = true;
  formError.value = "";
  const f = collectForm.value;
  try {
    const res = await api.post(`${base.value}/cod/${f.id}/collect`, {
      amount: Number(f.amount) || 0,
      collected_on: f.collected_on || undefined,
      collected_by: f.collected_by.trim() || undefined,
      notes: f.notes.trim() || undefined,
    });
    toast.success(res.data.message);
    collectForm.value = null;
    refresh();
  } catch (e) {
    formError.value = apiMessage(e, "Couldn't record the cash.");
  } finally {
    saving.value = false;
  }
}

async function saveSettle() {
  saving.value = true;
  formError.value = "";
  const f = settleForm.value;
  try {
    const res = await api.post(`${base.value}/cod/settle`, {
      order_ids: selected.value,
      settled_on: f.settled_on || undefined,
      reference: f.reference.trim() || undefined,
      notes: f.notes.trim() || undefined,
    });
    toast.success(res.data.message);
    settleForm.value = null;
    clearSelection();
    refresh();
  } catch (e) {
    formError.value = apiMessage(e, "Couldn't settle the cash.");
  } finally {
    saving.value = false;
  }
}

async function saveUndo() {
  saving.value = true;
  try {
    const res = await api.delete(`${base.value}/cod/${undoTarget.value.id}`);
    toast.success(res.data.message);
    selected.value = selected.value.filter((id) => id !== undoTarget.value.id);
    undoTarget.value = null;
    refresh();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't undo that."));
  } finally {
    saving.value = false;
  }
}

let searchTimer = null;
watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => load(1), 300);
});

onMounted(() => load(1));
</script>

<style scoped>
.fx-kpi.hero {
  background: linear-gradient(150deg, var(--bc-accent-soft) 0%, var(--v-surface) 60%);
  border-color: var(--bc-accent-muted);
}
.pm-ic {
  display: inline-grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: 8px;
}
.pm-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.muted {
  color: var(--v-faint);
  font-weight: 400;
}
.neg {
  color: var(--v-red-text);
}
.pos {
  color: var(--v-green-text);
}
.warn {
  color: var(--v-amber-text);
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 11px;
}

.pm-two {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 18px;
}
.pm-rate {
  flex: none;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
}
.pm-rate.good {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}
.pm-rate.ok {
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
}
.pm-rate.bad {
  background: var(--v-red-soft);
  color: var(--v-red-text);
}

/* PayMongo status tiles */
.pm-statuses {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}
.pm-status {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 10px 12px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-subtle);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, transform 0.15s;
}
.pm-status:hover {
  border-color: var(--bc-accent);
  transform: translateY(-1px);
}
.pm-status span {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  font-weight: 500;
  color: var(--v-text-2);
}
.pm-status i {
  width: 7px;
  height: 7px;
  border-radius: 50%;
}
.pm-status.green i { background: #48bb78; }
.pm-status.amber i { background: #f6ad55; }
.pm-status.red i { background: #fc8181; }
.pm-status.slate i { background: #a0aec0; }
.pm-status strong {
  font-size: 20px;
  font-weight: 600;
  color: var(--v-text);
  line-height: 1.2;
}
.pm-status small {
  font-size: 11.5px;
  color: var(--v-muted);
}
.pm-fees {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  margin-top: 14px;
  padding-top: 14px;
  border-top: 1px solid var(--v-border);
}
.pm-fees div {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.pm-fees span {
  font-size: 12px;
  color: var(--v-muted);
}
.pm-fees b {
  font-size: 16px;
  font-weight: 600;
  color: var(--v-text);
}
.pm-fees .warn b {
  color: var(--v-amber-text);
}
.pm-hint {
  margin: 10px 0 0;
}
.pm-providers {
  list-style: none;
  margin: 12px 0 0;
  padding: 0;
  display: grid;
  gap: 6px;
}
.pm-providers li {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto auto;
  align-items: center;
  gap: 10px;
  padding: 7px 10px;
  border-radius: 9px;
  background: var(--v-subtle);
  font-size: 12.5px;
}
.pm-provider {
  font-weight: 500;
  color: var(--v-text);
}
.pm-providers b {
  color: var(--v-text);
}

/* Cash pipeline */
.pm-pipeline {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  counter-reset: step;
}
.pm-pipeline li {
  position: relative;
}
.pm-pipeline li:not(:last-child)::after {
  content: "";
  position: absolute;
  top: 22px;
  right: -8px;
  width: 8px;
  height: 2px;
  background: var(--v-border-strong);
}
.pm-pipeline button {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  padding: 10px 12px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-subtle);
  font-family: inherit;
  text-align: left;
  cursor: pointer;
  transition: border-color 0.15s, transform 0.15s;
}
.pm-pipeline button:hover,
.pm-pipeline li.on button {
  border-color: var(--bc-accent);
  transform: translateY(-1px);
}
.pm-step {
  display: grid;
  place-items: center;
  width: 20px;
  height: 20px;
  margin-bottom: 4px;
  border-radius: 50%;
  font-size: 11px;
  font-weight: 600;
  background: var(--v-subtle-2);
  color: var(--v-text-2);
}
.pm-pipeline .amber .pm-step { background: var(--v-amber-soft); color: var(--v-amber-text); }
.pm-pipeline .blue .pm-step { background: var(--v-blue-soft); color: var(--v-blue-text); }
.pm-pipeline .green .pm-step { background: var(--v-green-soft); color: var(--v-green-text); }
.pm-step-label {
  font-size: 11.5px;
  font-weight: 500;
  color: var(--v-text-2);
}
.pm-pipeline strong {
  font-size: 20px;
  font-weight: 600;
  color: var(--v-text);
  line-height: 1.2;
}
.pm-pipeline small {
  font-size: 11.5px;
  color: var(--v-muted);
}

/* Reconciliation */
.pm-rec {
  margin-bottom: 18px;
}
.pm-rec-bar {
  display: flex;
  align-items: center;
  gap: 14px;
}
.pm-rec-bar p {
  margin: 0;
  font-size: 13px;
  color: var(--v-text-2);
  white-space: nowrap;
}
.pm-rec-bar b {
  color: var(--v-text);
}
.pm-meter {
  flex: 1;
  height: 10px;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-red-soft);
}
.pm-meter span {
  display: block;
  height: 100%;
  border-radius: 999px;
  background: #48bb78;
}
.pm-issues {
  list-style: none;
  margin: 14px 0 0;
  padding: 0;
  display: grid;
  gap: 8px;
}
.pm-issues li {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 10px 12px;
  border: 1px solid var(--v-amber-soft);
  border-radius: 12px;
  background: color-mix(in srgb, var(--v-amber-soft) 45%, transparent);
}
.pm-issues .grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.pm-issues strong {
  font-size: 13px;
  color: var(--v-text);
}
.pm-issues b {
  font-size: 13px;
  color: var(--v-text);
}
.pm-issue-ic {
  flex: none;
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
}
.pm-issue-ic :deep(svg) {
  width: 15px;
  height: 15px;
}
.pm-issue-msg {
  font-size: 12px;
  color: var(--v-amber-text);
}

/* List */
.pm-list {
  min-width: 0;
}
.pm-list-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-bottom: 14px;
}
.pm-toolbar .sp-select {
  width: auto;
  flex: none;
}
.pm-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.pm-rows {
  list-style: none;
  margin: 0;
  padding: 0;
  transition: opacity 0.2s;
}
.pm-rows.dim {
  opacity: 0.55;
}
.pm-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 6px;
  border-top: 1px solid var(--v-border);
  border-radius: 10px;
  transition: background 0.15s;
}
.pm-row:hover {
  background: var(--v-subtle);
}
.pm-row.picked {
  background: var(--bc-accent-soft);
}
.pm-row .grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 1px;
}
.pm-row .grow > * {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.pm-row strong {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.pm-sub {
  font-size: 12px;
  color: var(--v-faint);
}
.strong-sub {
  color: var(--v-text-2);
}
.pm-row .pm-issue-msg {
  white-space: normal;
}
.pm-check {
  flex: none;
  display: grid;
  place-items: center;
  width: 22px;
}
.pm-check input {
  width: 16px;
  height: 16px;
  accent-color: var(--bc-accent);
  cursor: pointer;
}
.pm-check.off {
  visibility: hidden;
}
.pm-chips-col {
  flex: none;
  width: 128px;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}
.pm-amt {
  flex: none;
  width: 170px;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 1px;
}
.pm-amt strong {
  font-size: 14px;
}
.pm-amt small {
  font-size: 11.5px;
  color: var(--v-muted);
  white-space: nowrap;
}
.pm-actions {
  flex: none;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 6px;
  min-width: 118px;
}
.fx-pager {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 10px;
  padding-top: 12px;
  border-top: 1px solid var(--v-border);
  font-size: 12.5px;
  color: var(--v-muted);
}
.fx-pager div {
  display: flex;
  gap: 6px;
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

@media (max-width: 1100px) {
  .pm-two {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 720px) {
  .pm-statuses,
  .pm-pipeline {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .pm-pipeline li::after {
    display: none;
  }
  .pm-providers li {
    grid-template-columns: minmax(0, 1fr) auto;
    row-gap: 2px;
  }
  .pm-providers .muted {
    grid-column: 1 / -1;
    grid-row: 2;
    font-size: 11.5px;
  }
  .pm-rec-bar {
    flex-direction: column;
    align-items: stretch;
  }
  .pm-rec-bar p {
    white-space: normal;
  }
  .pm-row {
    flex-wrap: wrap;
  }
  .pm-row .grow {
    flex-basis: calc(100% - 60px);
  }
  .pm-chips-col {
    width: auto;
    flex-direction: row;
    margin-left: 56px;
  }
  .pm-amt {
    width: auto;
    margin-left: auto;
  }
  .pm-actions:empty {
    display: none;
  }
  .pm-actions {
    min-width: 0;
    width: 100%;
    justify-content: flex-start;
    padding-left: 56px;
  }
}
</style>
