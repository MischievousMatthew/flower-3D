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

const lineQty = (item) => (answering.value ? Number(qty[item.id]) || 0 : item.confirmed);
const liveTotal = computed(() => (order.value?.items ?? []).reduce((s, i) => s + lineQty(i) * i.price, 0));
const allConfirmed = computed(() => (order.value?.items ?? []).every((i) => Number(qty[i.id]) === i.quantity));

const money = (n) => `₱${Number(n || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
const fmtDate = (d) => (d ? new Date(String(d).length === 10 ? `${d}T00:00:00` : d).toLocaleDateString("en-PH", { month: "long", day: "numeric", year: "numeric" }) : "");

function apply(data) {
  order.value = data;
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

async function confirmOrder() {
  clearErrors();
  for (const item of order.value.items) {
    const n = Number(qty[item.id]);
    if (!Number.isInteger(n) || n < 0 || n > item.quantity) errors[`items.${item.id}`] = `0 to ${item.quantity}.`;
  }
  if (Object.keys(errors).length) {
    formError.value = "Please check the highlighted amounts.";
    return;
  }
  busy.value = "confirm";
  try {
    const { data } = await api.post(`/supplier-orders/${token.value}/confirm`, {
      items: order.value.items.map((i) => ({ id: i.id, qty: Number(qty[i.id]) })),
      delivery_date: deliveryDate.value || null,
      note: note.value.trim() || null,
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
