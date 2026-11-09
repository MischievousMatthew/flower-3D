<template>
  <AuthShell :title="title" :lead="lead" wide>
    <template #top>
      <span v-if="order">Order {{ order.order_number }}</span>
    </template>
    <template #caption>
      <i>Flowers, on time.</i><br />
      Confirm what you can supply and when. The shop sees your answer straight away.
    </template>

    <!-- Loading -->
    <div v-if="loading" class="so-stack" aria-busy="true" aria-label="Loading the order">
      <div class="bc-skeleton so-sk so-sk--title"></div>
      <div class="bc-skeleton so-sk so-sk--table"></div>
      <div class="bc-skeleton so-sk so-sk--line"></div>
    </div>

    <!-- The link can't be used -->
    <AuthNotice v-else-if="loadError" tone="warn" :title="loadError.title" :body="loadError.body" />

    <div v-else class="so-stack">
      <!-- Where it stands -->
      <div class="so-status" :class="`so-status--${order.stage}`" role="status">
        <span class="so-dot" aria-hidden="true"></span>
        <div>
          <b>{{ STAGE[order.stage].title }}</b>
          <p>{{ STAGE[order.stage].text }}</p>
        </div>
      </div>

      <dl class="so-facts">
        <div><dt>Ordered</dt><dd>{{ fmtDate(order.order_date) }}</dd></div>
        <div><dt>{{ order.stage === "awaiting" ? "Wanted by" : "Delivery date" }}</dt><dd>{{ order.expected_date ? fmtDate(order.expected_date) : "Not set" }}</dd></div>
        <div><dt>Total</dt><dd>{{ money(liveTotal) }}</dd></div>
      </dl>

      <div v-if="order.notes" class="so-note">
        <span>Note from {{ order.shop_name }}</span>
        <p>{{ order.notes }}</p>
      </div>

      <!-- Payment -->
      <section v-if="order.payment" class="so-pay" aria-labelledby="so-pay-title">
        <div class="so-pay-head">
          <h2 id="so-pay-title">Payment</h2>
          <span v-if="order.payment.terms" class="so-tag">{{ order.payment.terms }}</span>
        </div>
        <p class="so-pay-text">{{ payText }}</p>

        <ul v-if="order.payment.payments?.length" class="so-paid">
          <li v-for="(m, i) in order.payment.payments" :key="i" :class="m.kind">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
            <div>
              <b>{{ m.kind === "refund" ? "You refunded" : "Paid to you" }} {{ money(m.amount) }}</b>
              <small>{{ fmtDate(m.paid_on) }}<template v-if="m.channel"> · {{ m.channel }}</template><template v-if="m.reference"> · ref. {{ m.reference }}</template></small>
            </div>
          </li>
        </ul>

        <!-- The account on file, once they've answered -->
        <template v-if="!answering && ['confirmed', 'shipped', 'received'].includes(order.stage)">
          <div v-if="order.payment.account && !editingAccount" class="so-acct">
            <div>
              <span>Paid into</span>
              <b>{{ order.payment.account.provider }} {{ order.payment.account.account_number }}</b>
              <small v-if="order.payment.account.account_name">{{ order.payment.account.account_name }}</small>
            </div>
            <button v-if="order.payment.can_change_account" type="button" class="af-btn ghost so-small" @click="startEditAccount">Change</button>
          </div>
          <div v-else-if="!editingAccount" class="so-acct so-acct--empty">
            <div><span>No account yet</span><small>Add where the shop should pay you.</small></div>
            <button type="button" class="af-btn ghost so-small" @click="startEditAccount">Add account</button>
          </div>
          <div v-if="editingAccount" class="so-acct-edit">
            <div class="so-acct-fields">
                <div class="so-seg" role="radiogroup" aria-label="Account type">
                  <label :class="{ on: acct.method === 'bank' }"><input v-model="acct.method" type="radio" value="bank" @change="acct.provider = ''" />Bank</label>
                  <label :class="{ on: acct.method === 'ewallet' }"><input v-model="acct.method" type="radio" value="ewallet" @change="acct.provider = ''" />E-wallet</label>
                </div>
                <div class="so-grid">
                  <div class="af-field">
                    <label class="af-label" for="so-edit-provider">{{ acct.method === "ewallet" ? "E-wallet" : "Bank" }}</label>
                    <select id="so-edit-provider" v-model="acct.provider" class="af-input" :aria-invalid="!!errors.payout_provider" @change="errors.payout_provider = ''">
                      <option value="">Choose one</option>
                      <option v-for="c in PROVIDERS[acct.method]" :key="c" :value="c">{{ c }}</option>
                    </select>
                    <p v-if="errors.payout_provider" class="af-note bad">{{ errors.payout_provider }}</p>
                  </div>
                  <div class="af-field">
                    <label class="af-label" for="so-edit-name">Name on the account</label>
                    <input id="so-edit-name" v-model="acct.name" class="af-input" maxlength="120" autocomplete="name" :aria-invalid="!!errors.payout_account_name" @input="errors.payout_account_name = ''" />
                    <p v-if="errors.payout_account_name" class="af-note bad">{{ errors.payout_account_name }}</p>
                  </div>
                  <div class="af-field">
                    <label class="af-label" for="so-edit-number">{{ acct.method === "ewallet" ? "Mobile number" : "Account number" }}</label>
                    <input id="so-edit-number" v-model="acct.number" class="af-input" maxlength="40" inputmode="numeric" autocomplete="off" :placeholder="acct.method === 'ewallet' ? '0917 123 4567' : '0012 3456 7890'" :aria-invalid="!!errors.payout_account_number" @input="errors.payout_account_number = ''" />
                    <p v-if="errors.payout_account_number" class="af-note bad">{{ errors.payout_account_number }}</p>
                  </div>
                </div>
              </div>
            <AuthNotice v-if="formError" tone="warn" error :title="formError" body="" />
            <div class="so-actions">
              <button type="button" class="af-btn" :class="{ busy: busy === 'payout' }" :disabled="!!busy" @click="saveAccount">{{ busy === "payout" ? "Saving…" : "Save account" }}</button>
              <button type="button" class="af-btn ghost" :disabled="!!busy" @click="editingAccount = false">Cancel</button>
            </div>
          </div>
        </template>
      </section>

      <!-- Items -->
      <form class="af-form" novalidate @submit.prevent="confirmOrder">
        <div class="so-table" role="table" aria-label="Items ordered">
          <div class="so-row so-row--head" role="row">
            <span role="columnheader">Item</span>
            <span role="columnheader">Ordered</span>
            <span role="columnheader">{{ answering ? "You can supply" : "Confirmed" }}</span>
            <span role="columnheader" class="num">Amount</span>
          </div>
          <div v-for="item in order.items" :key="item.id" class="so-row" role="row">
            <span role="cell" class="so-name">
              <b>{{ item.name }}</b>
              <small>{{ money(item.price) }}{{ item.unit ? ` / ${item.unit}` : "" }}</small>
            </span>
            <span role="cell" data-label="Ordered">{{ item.quantity.toLocaleString() }} {{ item.unit || "" }}</span>
            <span role="cell" data-label="You can supply">
              <template v-if="answering">
                <label class="sr-only" :for="`qty-${item.id}`">How many {{ item.name }} you can supply</label>
                <input
                  :id="`qty-${item.id}`"
                  v-model.number="qty[item.id]"
                  class="af-input so-qty"
                  type="number"
                  min="0"
                  :max="item.quantity"
                  inputmode="numeric"
                  :aria-invalid="!!errors[`items.${item.id}`]"
                  @input="errors[`items.${item.id}`] = ''"
                />
                <small v-if="errors[`items.${item.id}`]" class="af-note bad">{{ errors[`items.${item.id}`] }}</small>
              </template>
              <template v-else>{{ item.confirmed.toLocaleString() }} {{ item.unit || "" }}</template>
            </span>
            <span role="cell" class="num" data-label="Amount">{{ money(lineQty(item) * item.price) }}</span>
          </div>
        </div>

        <!-- Confirm -->
        <template v-if="answering">
          <div class="so-grid">
            <div class="af-field">
              <label class="af-label" for="so-date">When can you deliver?</label>
              <input id="so-date" v-model="deliveryDate" class="af-input" type="date" :min="today" :aria-invalid="!!errors.delivery_date" @input="errors.delivery_date = ''" />
              <p class="af-note" :class="{ bad: errors.delivery_date }">{{ errors.delivery_date || "Leave it as is if you can deliver on the date they asked for." }}</p>
            </div>
            <div class="af-field">
              <label class="af-label" for="so-note">Message for the shop <span class="so-opt">(optional)</span></label>
              <textarea id="so-note" v-model="note" class="af-input so-textarea" rows="3" maxlength="1000" placeholder="e.g. Tulips are short this week, the rest is ready."></textarea>
            </div>
          </div>

          <fieldset class="so-acct-box">
            <legend class="af-label">Where should {{ order.shop_name }} pay you? <span class="so-opt">{{ payBefore ? "(needed: you're paid before delivery)" : "(optional)" }}</span></legend>
            <div v-if="order.payment?.account" class="so-saved" role="radiogroup" aria-label="Account">
              <label :class="{ on: acct.useSaved }"><input v-model="acct.useSaved" type="radio" :value="true" />Use {{ order.payment.account.provider }} {{ order.payment.account.account_number }}<small v-if="order.payment.account.account_name"> · {{ order.payment.account.account_name }}</small></label>
              <label :class="{ on: !acct.useSaved }"><input v-model="acct.useSaved" type="radio" :value="false" />Use a different account</label>
            </div>
            <template v-if="!order.payment?.account || !acct.useSaved">
              <div class="so-acct-fields">
                <div class="so-seg" role="radiogroup" aria-label="Account type">
                  <label :class="{ on: acct.method === 'bank' }"><input v-model="acct.method" type="radio" value="bank" @change="acct.provider = ''" />Bank</label>
                  <label :class="{ on: acct.method === 'ewallet' }"><input v-model="acct.method" type="radio" value="ewallet" @change="acct.provider = ''" />E-wallet</label>
                </div>
                <div class="so-grid">
                  <div class="af-field">
                    <label class="af-label" for="so-conf-provider">{{ acct.method === "ewallet" ? "E-wallet" : "Bank" }}</label>
                    <select id="so-conf-provider" v-model="acct.provider" class="af-input" :aria-invalid="!!errors.payout_provider" @change="errors.payout_provider = ''">
                      <option value="">Choose one</option>
                      <option v-for="c in PROVIDERS[acct.method]" :key="c" :value="c">{{ c }}</option>
                    </select>
                    <p v-if="errors.payout_provider" class="af-note bad">{{ errors.payout_provider }}</p>
                  </div>
                  <div class="af-field">
                    <label class="af-label" for="so-conf-name">Name on the account</label>
                    <input id="so-conf-name" v-model="acct.name" class="af-input" maxlength="120" autocomplete="name" :aria-invalid="!!errors.payout_account_name" @input="errors.payout_account_name = ''" />
                    <p v-if="errors.payout_account_name" class="af-note bad">{{ errors.payout_account_name }}</p>
                  </div>
                  <div class="af-field">
                    <label class="af-label" for="so-conf-number">{{ acct.method === "ewallet" ? "Mobile number" : "Account number" }}</label>
                    <input id="so-conf-number" v-model="acct.number" class="af-input" maxlength="40" inputmode="numeric" autocomplete="off" :placeholder="acct.method === 'ewallet' ? '0917 123 4567' : '0012 3456 7890'" :aria-invalid="!!errors.payout_account_number" @input="errors.payout_account_number = ''" />
                    <p v-if="errors.payout_account_number" class="af-note bad">{{ errors.payout_account_number }}</p>
                  </div>
                </div>
              </div>
            </template>
          </fieldset>

          <AuthNotice v-if="formError" tone="warn" error :title="formError" body="" />
          <div class="so-actions">
            <button type="submit" class="af-btn" :class="{ busy: busy === 'confirm' }" :disabled="!!busy">
              {{ busy === "confirm" ? "Confirming…" : allConfirmed ? "Confirm the order" : "Confirm what I can supply" }}
            </button>
            <button type="button" class="af-btn ghost" :disabled="!!busy" @click="declineOpen = !declineOpen">I can't supply this order</button>
          </div>

          <!-- Decline -->
          <div v-if="declineOpen" class="so-decline">
            <div class="af-field">
              <label class="af-label" for="so-reason">Tell the shop why</label>
              <textarea id="so-reason" v-model="reason" class="af-input so-textarea" rows="3" maxlength="1000" :aria-invalid="!!errors.reason" placeholder="e.g. We're out of tulips until next month." @input="errors.reason = ''"></textarea>
              <p class="af-note bad" aria-live="polite">{{ errors.reason }}</p>
            </div>
            <button type="button" class="af-btn danger" :class="{ busy: busy === 'decline' }" :disabled="!!busy" @click="declineOrder">
              {{ busy === "decline" ? "Sending…" : "Decline the order" }}
            </button>
          </div>
        </template>
      </form>

      <!-- Ship -->
      <section v-if="order.stage === 'confirmed'" class="so-ship" aria-labelledby="so-ship-title">
        <h2 id="so-ship-title">Sent it on its way?</h2>
        <div class="af-field">
          <label class="af-label" for="so-ship-note">Delivery details <span class="so-opt">(optional)</span></label>
          <input id="so-ship-note" v-model="shipNote" class="af-input" maxlength="500" placeholder="e.g. Lalamove, driver Jun, 0917 123 4567" />
        </div>
        <AuthNotice v-if="formError" tone="warn" error :title="formError" body="" />
        <button type="button" class="af-btn" :class="{ busy: busy === 'ship' }" :disabled="!!busy" @click="markShipped">
          {{ busy === "ship" ? "Saving…" : "Mark as shipped" }}
        </button>
      </section>

      <p v-if="order.supplier_note && !answering" class="so-said">You said: “{{ order.supplier_note }}”</p>

      <p class="af-note so-expiry">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
        This private link works until {{ fmtDate(order.expires_at) }}. Don't share it.
      </p>
    </div>
  </AuthShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute } from "vue-router";
import api from "../../plugins/axios";
import AuthShell from "../../components/auth/AuthShell.vue";
import AuthNotice from "../../components/auth/AuthNotice.vue";

/** A supplier answers a purchase order from the private link in its email (no account) */
const STAGE = {
  awaiting: { title: "Waiting for your answer", text: "Check the items, change any amount you can't fully supply, then confirm." },
  confirmed: { title: "You confirmed this order", text: "Mark it shipped below when it's on the way." },
  shipped: { title: "On its way", text: "The shop has been told it's coming. Thank you!" },
  received: { title: "Received by the shop", text: "The shop has received this order. Nothing more to do." },
  declined: { title: "You declined this order", text: "The shop has been told and will order elsewhere." },
  closed: { title: "This order is closed", text: "Nothing more to do here." },
};

const route = useRoute();
const token = computed(() => route.params.token);

const loading = ref(true);
const loadError = ref(null);
const order = ref(null);
const qty = reactive({});
const errors = reactive({});
const deliveryDate = ref("");
const note = ref("");
const reason = ref("");
const shipNote = ref("");
const PROVIDERS = {
  bank: ["BDO", "BPI", "Metrobank", "Landbank", "PNB", "Security Bank", "UnionBank", "RCBC", "China Bank", "EastWest Bank", "Other bank"],
  ewallet: ["GCash", "Maya", "GrabPay", "ShopeePay", "Coins.ph", "Other e-wallet"],
};
const acct = reactive({ useSaved: false, method: "bank", provider: "", name: "", number: "" });
const editingAccount = ref(false);
const declineOpen = ref(false);
const busy = ref("");
const formError = ref("");

const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
const answering = computed(() => order.value?.stage === "awaiting");
const title = computed(() => (order.value ? `Purchase order from ${order.value.shop_name}` : "Purchase order"));
const lead = computed(() => {
  if (!order.value) return "";
  const name = (order.value.contact_person || "").split(" ")[0];
  return `${name ? `Hello ${name}. ` : ""}${order.value.shop_name} would like to order from ${order.value.supplier_name || "you"}.`;
});

const payBefore = computed(() => order.value?.payment?.timing === "before_delivery");
const payText = computed(() => {
  const o = order.value;
  const p = o?.payment;
  if (!p) return "";
  const shop = o.shop_name;
  const paid = p.paid > 0 ? ` ${money(p.paid)} paid so far.` : "";
  if (payBefore.value) {
    return `${shop} pays before delivery, once you confirm, for the items you confirm${answering.value ? ` (${money(liveTotal.value)} as it stands)` : ` (${money(liveTotal.value)})`}.${paid}`;
  }
  return `${shop} pays after delivery, for what arrives${p.due_days ? `, within ${p.due_days} days of delivery` : ""}.${paid}`;
});
const lineQty = (item) => (answering.value ? Number(qty[item.id]) || 0 : item.confirmed);
const liveTotal = computed(() => (order.value?.items ?? []).reduce((s, i) => s + lineQty(i) * i.price, 0));
const allConfirmed = computed(() => (order.value?.items ?? []).every((i) => Number(qty[i.id]) === i.quantity));

const money = (n) => `₱${Number(n || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const fmtDate = (d) => (d ? new Date(String(d).length === 10 ? `${d}T00:00:00` : d).toLocaleDateString("en-PH", { month: "long", day: "numeric", year: "numeric" }) : "");

function apply(data) {
  order.value = data;
  Object.assign(acct, { useSaved: !!data.payment?.account, method: "bank", provider: "", name: "", number: "" });
  editingAccount.value = false;
  for (const item of data.items) qty[item.id] = item.confirmed;
  deliveryDate.value = data.expected_date && data.expected_date >= today ? data.expected_date : "";
}

async function load() {
  loading.value = true;
  loadError.value = null;
  try {
    const { data } = await api.get(`/supplier-orders/${token.value}`);
    apply(data.data);
  } catch (e) {
    const code = e.response?.data?.code;
    loadError.value = {
      title: code === "link_expired" ? "This link has expired" : code === "link_replaced" ? "There's a newer link" : "This link can't be used",
      body: e.response?.data?.message || "Check your email for the most recent message about this order.",
    };
  } finally {
    loading.value = false;
  }
}

function clearErrors() {
  formError.value = "";
  Object.keys(errors).forEach((k) => delete errors[k]);
}

function showError(e, fallback) {
  const data = e.response?.data ?? {};
  for (const [key, list] of Object.entries(data.errors ?? {})) errors[key] = [].concat(list)[0];
  formError.value = data.message || (!e.response ? "Your answer didn't reach the shop. Check your connection and try again." : fallback);
  // Answered from another tab: show where it stands now
  if (data.code === "already_answered") load();
}

const PROVIDER_ERRORS = { provider: "payout_provider", name: "payout_account_name", number: "payout_account_number" };
// Checks the account fields; true when they're filled in properly (or rightly left empty)
function checkAccount(required) {
  const any = acct.provider || acct.name.trim() || acct.number.trim();
  if (!required && !any) return true;
  if (!acct.provider) errors[PROVIDER_ERRORS.provider] = "Choose the bank or e-wallet.";
  if (!acct.name.trim()) errors[PROVIDER_ERRORS.name] = "Enter the name on the account.";
  if (!/^[0-9 -]{6,40}$/.test(acct.number.trim())) errors[PROVIDER_ERRORS.number] = "Use numbers only (6 or more); spaces and dashes are fine.";
  return !errors.payout_provider && !errors.payout_account_name && !errors.payout_account_number;
}
const accountBody = () => ({ payout_method: acct.method, payout_provider: acct.provider, payout_account_name: acct.name.trim(), payout_account_number: acct.number.trim() });

function startEditAccount() {
  clearErrors();
  const a = order.value.payment?.account;
  Object.assign(acct, { useSaved: false, method: a?.method || "bank", provider: a?.provider || "", name: a?.account_name || "", number: "" });
  editingAccount.value = true;
}

async function saveAccount() {
  clearErrors();
  if (!checkAccount(true)) return;
  busy.value = "payout";
  try {
    const { data } = await api.post(`/supplier-orders/${token.value}/payout`, accountBody());
    apply(data.data);
  } catch (e) {
    showError(e, "We couldn't save the account. Please try again.");
  } finally {
    busy.value = "";
  }
}

async function confirmOrder() {
  clearErrors();
  const useSaved = !!order.value.payment?.account && acct.useSaved;
  if (!useSaved) checkAccount(payBefore.value);
  for (const item of order.value.items) {
    const n = Number(qty[item.id]);
    if (!Number.isInteger(n) || n < 0 || n > item.quantity) errors[`items.${item.id}`] = `0 to ${item.quantity}.`;
  }
  if (Object.keys(errors).length) {
    formError.value = Object.keys(errors).some((k) => k.startsWith("items.")) ? "Please check the highlighted amounts." : "Please check the payment account.";
    return;
  }
  busy.value = "confirm";
  try {
    const { data } = await api.post(`/supplier-orders/${token.value}/confirm`, {
      items: order.value.items.map((i) => ({ id: i.id, qty: Number(qty[i.id]) })),
      delivery_date: deliveryDate.value || null,
      note: note.value.trim() || null,
      ...(useSaved ? { use_saved: true } : acct.number.trim() ? accountBody() : {}),
    });
    apply(data.data);
    window.scrollTo({ top: 0, behavior: "smooth" });
  } catch (e) {
    showError(e, "We couldn't save your answer. Please try again.");
  } finally {
    busy.value = "";
  }
}

async function declineOrder() {
  clearErrors();
  if (reason.value.trim().length < 3) {
    errors.reason = "Tell the shop why, so they can order elsewhere.";
    return;
  }
  busy.value = "decline";
  try {
    const { data } = await api.post(`/supplier-orders/${token.value}/decline`, { reason: reason.value.trim() });
    apply(data.data);
    window.scrollTo({ top: 0, behavior: "smooth" });
  } catch (e) {
    showError(e, "We couldn't send your answer. Please try again.");
  } finally {
    busy.value = "";
  }
}

async function markShipped() {
  clearErrors();
  busy.value = "ship";
  try {
    const { data } = await api.post(`/supplier-orders/${token.value}/shipped`, { note: shipNote.value.trim() || null });
    apply(data.data);
    window.scrollTo({ top: 0, behavior: "smooth" });
  } catch (e) {
    showError(e, "We couldn't save that. Please try again.");
  } finally {
    busy.value = "";
  }
}

onMounted(load);
</script>

<style scoped>
.so-stack {
  display: grid;
  gap: 18px;
}

/* Where it stands */
.so-status {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 14px 16px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #fff;
}
.so-status b {
  display: block;
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
}
.so-status p {
  margin: 2px 0 0;
  font-size: 14px;
  line-height: 1.5;
  color: var(--ink-soft);
}
.so-dot {
  flex: none;
  width: 10px;
  height: 10px;
  margin-top: 6px;
  border-radius: 50%;
  background: #d4b27a;
}
.so-status--confirmed .so-dot,
.so-status--shipped .so-dot,
.so-status--received .so-dot {
  background: #4db173;
}
.so-status--declined .so-dot,
.so-status--closed .so-dot {
  background: #9aa3ad;
}

/* Payment */
.so-pay {
  display: grid;
  gap: 10px;
  padding: 16px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #fff;
}
.so-pay-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}
.so-pay-head h2 {
  margin: 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
}
.so-tag {
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--ivory);
  font-size: 12px;
  font-weight: 600;
  color: var(--ink-soft);
}
.so-pay-text {
  margin: 0;
  font-size: 14px;
  line-height: 1.6;
  color: var(--ink);
}
.so-paid {
  display: grid;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.so-paid li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 12px;
  background: rgba(77, 177, 115, 0.1);
}
.so-paid svg {
  flex: none;
  width: 18px;
  height: 18px;
  margin-top: 1px;
  fill: none;
  stroke: #2f8a52;
  stroke-width: 2.4;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.so-paid b {
  display: block;
  font-size: 14px;
  color: var(--ink);
}
.so-paid small {
  font-size: 12.5px;
  color: var(--ink-soft);
}
.so-acct {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
}
.so-acct > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}
.so-acct span {
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: var(--ink-soft);
}
.so-acct b {
  font-size: 14px;
  color: var(--ink);
}
.so-acct small {
  font-size: 12.5px;
  color: var(--ink-soft);
}
.so-acct--empty {
  border-style: dashed;
}
.so-small {
  width: auto;
  min-height: 40px;
  padding: 0 16px;
}
.so-acct-edit {
  display: grid;
  gap: 12px;
}
.so-acct-box {
  display: grid;
  gap: 12px;
  min-width: 0;
  margin: 0;
  padding: 14px;
  border: 1px solid var(--line);
  border-radius: 14px;
}
.so-acct-box legend {
  padding: 0 4px;
}
.so-acct-fields {
  display: grid;
  gap: 12px;
}
.so-seg,
.so-saved {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.so-seg label,
.so-saved label {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  min-height: 44px;
  padding: 0 14px;
  border: 1px solid var(--line);
  border-radius: 999px;
  background: #fff;
  font-size: 14px;
  color: var(--ink);
  cursor: pointer;
}
.so-saved label {
  border-radius: 12px;
}
.so-seg input,
.so-saved input {
  position: absolute;
  opacity: 0;
  pointer-events: none;
}
.so-seg label:has(input:focus-visible),
.so-saved label:has(input:focus-visible) {
  outline: 2px solid #4db173;
  outline-offset: 2px;
}
.so-seg label.on,
.so-saved label.on {
  border-color: #4db173;
  background: rgba(77, 177, 115, 0.1);
  font-weight: 600;
}
.so-saved small {
  font-weight: 400;
  color: var(--ink-soft);
}

.so-facts {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}
.so-facts div {
  padding: 12px 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: #fff;
}
.so-facts dt {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--ink-soft);
}
.so-facts dd {
  margin: 4px 0 0;
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
  font-variant-numeric: tabular-nums;
}

.so-note {
  padding: 12px 14px;
  border-left: 3px solid #4db173;
  border-radius: 4px 12px 12px 4px;
  background: var(--ivory);
}
.so-note span {
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--ink-soft);
}
.so-note p {
  margin: 2px 0 0;
  font-size: 14.5px;
  line-height: 1.55;
  color: var(--ink);
  white-space: pre-line;
}

/* Items */
.so-table {
  display: grid;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #fff;
  overflow: hidden;
}
.so-row {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1.2fr) minmax(0, 1fr);
  gap: 12px;
  align-items: center;
  padding: 12px 16px;
  border-top: 1px solid var(--line);
  font-size: 14px;
  color: var(--ink);
  font-variant-numeric: tabular-nums;
}
.so-row--head {
  border-top: 0;
  background: var(--ivory);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  color: var(--ink-soft);
}
.so-name {
  display: grid;
  gap: 2px;
}
.so-name b {
  font-weight: 600;
}
.so-name small {
  font-size: 12.5px;
  color: var(--ink-soft);
}
.num {
  text-align: right;
}
.so-qty {
  max-width: 120px;
  height: 40px;
}

.so-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}
.so-opt {
  font-weight: 400;
  color: var(--ink-soft);
}
.so-textarea {
  height: auto;
  padding: 12px 14px;
  line-height: 1.55;
  resize: vertical;
}
.so-actions {
  display: grid;
  gap: 10px;
}
.so-decline,
.so-ship {
  display: grid;
  gap: 12px;
  padding: 16px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #fff;
}
.so-ship h2 {
  margin: 0;
  font-size: 18px;
  color: var(--ink);
}
.af-btn.danger {
  background: #b4443c;
}
.so-said {
  margin: 0;
  font-size: 14px;
  color: var(--ink-soft);
}
.so-expiry {
  display: flex;
  align-items: center;
  gap: 6px;
}
.so-expiry svg {
  width: 16px;
  height: 16px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

/* Skeleton */
.so-sk {
  height: 14px;
}
.so-sk--title {
  width: 55%;
  height: 22px;
}
.so-sk--table {
  height: 180px;
  border-radius: 14px;
}
.so-sk--line {
  width: 70%;
}

@media (max-width: 640px) {
  .so-facts,
  .so-grid {
    grid-template-columns: 1fr;
  }
  .so-row--head {
    display: none;
  }
  .so-row {
    grid-template-columns: 1fr;
    gap: 6px;
  }
  .so-row > [data-label]::before {
    content: attr(data-label) ": ";
    color: var(--ink-soft);
    font-size: 12.5px;
  }
  .num {
    text-align: left;
  }
}
</style>
