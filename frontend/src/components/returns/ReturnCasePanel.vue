<template>
  <section class="rc" :class="`rc--${role}`" aria-live="polite">
    <!-- Loading -->
    <div v-if="!data" class="rc-skel">
      <span v-if="loadError" class="rc-error">
        {{ loadError }} <button type="button" class="rc-link" @click="load">Try again</button>
      </span>
      <template v-else>
        <span class="rc-sk" style="height: 18px; width: 45%"></span>
        <span class="rc-sk" style="height: 40px"></span>
        <span class="rc-sk" style="height: 90px"></span>
      </template>
    </div>

    <template v-else>
      <!-- Header -->
      <header class="rc-head">
        <div>
          <p class="rc-eyebrow">{{ data.type === "return" ? "Return the item" : "Refund only · keep the item" }} · {{ data.reason_label }}</p>
          <h3 class="rc-title">{{ STAGE[data.stage]?.title ?? data.stage }}</h3>
          <p class="rc-sub">{{ stageHint }}</p>
        </div>
        <span class="rc-chip" :class="chipTone">{{ turnLabel }}</span>
      </header>

      <!-- Progress -->
      <ol v-if="!isClosedWithoutDeal" class="rc-steps">
        <li v-for="(step, i) in steps" :key="step.key" :class="{ done: i < stepIndex, now: i === stepIndex && data.stage !== 'completed' }">
          <span class="rc-dot">{{ i < stepIndex || data.stage === "completed" ? "✓" : i + 1 }}</span>
          <span>{{ step.label }}</span>
        </li>
      </ol>
      <p v-else class="rc-closed">
        <strong>{{ data.stage === "rejected" ? "Declined by the shop" : "Withdrawn by the customer" }}</strong>
        <span v-if="data.closed_reason"> — “{{ data.closed_reason }}”</span>
      </p>

      <!-- What was asked / agreed -->
      <div class="rc-terms">
        <div>
          <span class="rc-lbl">Asked for</span>
          <strong>{{ describe(data.requested.resolution, data.requested.amount) }}</strong>
        </div>
        <div v-if="data.settled">
          <span class="rc-lbl">Agreed</span>
          <strong class="rc-agreed">{{ describe(data.settled.resolution, data.settled.amount) }}</strong>
        </div>
        <div>
          <span class="rc-lbl">Order total</span>
          <strong>{{ money(data.order?.total_amount) }}</strong>
        </div>
      </div>

      <!-- ═══ Agreeing ═══ -->
      <div v-if="data.status === 'pending' && data.offer" class="rc-box">
        <p class="rc-offer">
          <span class="rc-lbl">{{ data.offer.by === "vendor" ? "Shop's offer" : "Customer's request" }}</span>
          <strong>{{ describe(data.offer.resolution, data.offer.amount) }}</strong>
        </p>

        <template v-if="canAct">
          <div v-if="!composer.open" class="rc-actions">
            <template v-if="data.offer.by !== role">
              <button type="button" class="rc-btn rc-btn--main" :disabled="!!busy" @click="act('accept')">
                {{ busy === "accept" ? "Saving…" : role === "vendor" ? "Accept request" : "Accept offer" }}
              </button>
              <button type="button" class="rc-btn" :disabled="!!busy" @click="openOffer">
                {{ role === "vendor" ? "Offer something else" : "Counter-offer" }}
              </button>
            </template>
            <template v-else>
              <span class="rc-wait">Waiting for the {{ role === "vendor" ? "customer" : "shop" }} to reply.</span>
              <button type="button" class="rc-btn" :disabled="!!busy" @click="openOffer">Change my offer</button>
            </template>
            <button v-if="role === 'vendor'" type="button" class="rc-btn rc-btn--danger" :disabled="!!busy" @click="closing.open = true">Decline request</button>
            <button v-if="role === 'customer'" type="button" class="rc-btn rc-btn--quiet" :disabled="!!busy" @click="closing.open = true">Withdraw request</button>
          </div>

          <!-- Offer form -->
          <form v-else class="rc-form" @submit.prevent="sendOffer">
            <div class="rc-options" role="radiogroup" aria-label="What do you propose?">
              <label v-for="o in RESOLUTIONS" :key="o.value" class="rc-option" :class="{ on: composer.resolution === o.value, off: o.value !== 'replacement' && !refundable }">
                <input v-model="composer.resolution" type="radio" :value="o.value" :disabled="o.value !== 'replacement' && !refundable" />
                <span><strong>{{ o.label }}</strong><small>{{ o.hint }}</small></span>
              </label>
            </div>
            <p v-if="!refundable" class="rc-note">This order isn't marked as paid, so only a replacement can be offered.</p>
            <label v-if="composer.resolution === 'partial_refund'" class="rc-field">
              <span>Amount (less than {{ money(data.order?.total_amount) }})</span>
              <input v-model.number="composer.amount" type="number" min="1" step="0.01" :max="data.order?.total_amount" class="rc-input" required />
            </label>
            <label class="rc-field">
              <span>Note (optional)</span>
              <textarea v-model="composer.note" class="rc-input" rows="2" maxlength="1000" placeholder="Explain your offer"></textarea>
            </label>
            <div class="rc-actions">
              <button type="button" class="rc-btn rc-btn--quiet" :disabled="!!busy" @click="composer.open = false">Cancel</button>
              <button type="submit" class="rc-btn rc-btn--main" :disabled="!!busy">{{ busy === "offer" ? "Sending…" : "Send offer" }}</button>
            </div>
          </form>

          <!-- Decline / withdraw -->
          <form v-if="closing.open" class="rc-form rc-form--warn" @submit.prevent="closeCase">
            <label class="rc-field">
              <span>{{ role === "vendor" ? "Why are you declining? The customer will see this." : "Anything to add? (optional)" }}</span>
              <textarea v-model="closing.reason" class="rc-input" rows="2" maxlength="500" :required="role === 'vendor'"></textarea>
            </label>
            <div class="rc-actions">
              <button type="button" class="rc-btn rc-btn--quiet" :disabled="!!busy" @click="closing.open = false">Keep it open</button>
              <button type="submit" class="rc-btn rc-btn--danger" :disabled="!!busy">
                {{ busy ? "Saving…" : role === "vendor" ? "Decline request" : "Withdraw request" }}
              </button>
            </div>
          </form>
        </template>
      </div>

      <!-- ═══ Carrying it out ═══ -->
      <div v-if="data.status === 'approved'" class="rc-box">
        <!-- Item coming back -->
        <template v-if="data.stage === 'awaiting_return'">
          <p v-if="role === 'customer'">Send the item back to the shop. Use the messages below to agree on pickup or drop-off.</p>
          <p v-else-if="role === 'vendor'">Arrange the pickup with the customer below, then confirm once the item reaches you.</p>
          <p v-else>Waiting for the item to reach the shop.</p>
          <div v-if="role === 'vendor' && canAct" class="rc-actions">
            <button type="button" class="rc-btn rc-btn--main" :disabled="!!busy" @click="act('item-received')">
              {{ busy === "item-received" ? "Saving…" : "I've received the item" }}
            </button>
          </div>
        </template>

        <!-- Refund -->
        <template v-else-if="data.stage === 'awaiting_refund'">
          <template v-if="role === 'vendor' && canAct">
            <p>Send <strong>{{ money(data.settled.amount) }}</strong> to the customer, then record how you sent it.</p>
            <form class="rc-form" @submit.prevent="sendRefund">
              <div class="rc-row">
                <label class="rc-field">
                  <span>Sent by</span>
                  <select v-model="refund.method" class="rc-input" required>
                    <option v-for="(label, key) in data.refund_methods" :key="key" :value="key">{{ label }}</option>
                  </select>
                </label>
                <label class="rc-field">
                  <span>Reference number (optional)</span>
                  <input v-model="refund.reference" class="rc-input" maxlength="120" placeholder="e.g. GCash ref no." />
                </label>
              </div>
              <label class="rc-field">
                <span>Note (optional)</span>
                <input v-model="refund.note" class="rc-input" maxlength="500" />
              </label>
              <div class="rc-actions">
                <button type="submit" class="rc-btn rc-btn--main" :disabled="!!busy">{{ busy === "refund-sent" ? "Saving…" : "Mark refund as sent" }}</button>
              </div>
            </form>
          </template>
          <p v-else>Waiting for the shop to send the {{ money(data.settled.amount) }} refund.</p>
        </template>

        <template v-else-if="data.stage === 'confirm_refund'">
          <p>
            The shop sent <strong>{{ money(data.settled.amount) }}</strong> by {{ data.refund?.method_label }}
            <template v-if="data.refund?.reference"> (ref <strong>{{ data.refund.reference }}</strong>)</template>.
          </p>
          <div v-if="role === 'customer'" class="rc-actions">
            <button type="button" class="rc-btn rc-btn--main" :disabled="!!busy" @click="act('confirm-refund')">{{ busy === "confirm-refund" ? "Saving…" : "Yes, I received it" }}</button>
            <button type="button" class="rc-btn" :disabled="!!busy" @click="act('refund-missing')">Not yet</button>
          </div>
          <p v-else class="rc-wait">Waiting for the customer to confirm they received it.</p>
        </template>

        <!-- Replacement -->
        <template v-else-if="data.stage === 'awaiting_replacement'">
          <template v-if="role === 'vendor' && canAct">
            <p>Send the replacement, then mark it as sent.</p>
            <form class="rc-form" @submit.prevent="act('replacement-sent', { note: replacementNote })">
              <label class="rc-field">
                <span>Delivery details (optional)</span>
                <input v-model="replacementNote" class="rc-input" maxlength="255" placeholder="e.g. Courier and expected time" />
              </label>
              <div class="rc-actions">
                <button type="submit" class="rc-btn rc-btn--main" :disabled="!!busy">{{ busy === "replacement-sent" ? "Saving…" : "Mark replacement as sent" }}</button>
              </div>
            </form>
          </template>
          <p v-else>Waiting for the shop to send the replacement.</p>
        </template>

        <template v-else-if="data.stage === 'confirm_replacement'">
          <p>The shop sent the replacement.</p>
          <div v-if="role === 'customer'" class="rc-actions">
            <button type="button" class="rc-btn rc-btn--main" :disabled="!!busy" @click="act('confirm-replacement')">{{ busy === "confirm-replacement" ? "Saving…" : "Yes, I received it" }}</button>
            <button type="button" class="rc-btn" :disabled="!!busy" @click="act('replacement-missing')">Not yet</button>
          </div>
          <p v-else class="rc-wait">Waiting for the customer to confirm it arrived.</p>
        </template>
      </div>

      <p v-if="data.stage === 'completed'" class="rc-done">
        ✓ Settled {{ fmtDate(data.completed_at) }}:
        {{ describe(data.settled?.resolution, data.settled?.amount) }}<template v-if="data.refund?.reference"> · ref {{ data.refund.reference }}</template>
      </p>

      <!-- Evidence -->
      <figure v-if="data.media_url" class="rc-media">
        <video v-if="data.media_type === 'video'" :src="data.media_url" controls></video>
        <a v-else :href="data.media_url" target="_blank" rel="noopener"><img :src="data.media_url" alt="Photo from the customer" /></a>
        <figcaption>Proof from the customer</figcaption>
      </figure>

      <!-- Conversation -->
      <div class="rc-thread">
        <h4 class="rc-lbl">Conversation</h4>
        <ul>
          <li v-for="m in data.messages" :key="m.id" :class="[`from-${m.author_role}`, { event: m.kind !== 'message' && m.kind !== 'opened', mine: isMine(m) }]">
            <template v-if="m.kind === 'message' || m.kind === 'opened'">
              <span class="rc-who">{{ m.author_name }} · {{ fmtDateTime(m.at) }}</span>
              <p class="rc-bubble">
                <template v-if="m.kind === 'opened'"><strong>Asked for {{ describe(m.resolution, m.amount) }}.</strong><br /></template>{{ m.body }}
              </p>
              <a v-if="m.media_url && m.kind === 'message'" :href="m.media_url" target="_blank" rel="noopener" class="rc-attach">View attachment</a>
            </template>
            <p v-else class="rc-event">
              <strong>{{ m.author_name }}</strong> {{ eventText(m) }}
              <span v-if="m.body" class="rc-event-note">“{{ m.body }}”</span>
              <span class="rc-event-time">{{ fmtDateTime(m.at) }}</span>
            </p>
          </li>
        </ul>

        <form v-if="role !== 'admin' && isOpen" class="rc-compose" @submit.prevent="sendMessage">
          <textarea v-model="message" class="rc-input" rows="2" maxlength="2000" :placeholder="role === 'vendor' ? 'Message the customer' : 'Message the shop'" @keydown.enter.exact.prevent="sendMessage"></textarea>
          <button type="submit" class="rc-btn rc-btn--main" :disabled="!!busy || !message.trim()">{{ busy === "message" ? "Sending…" : "Send" }}</button>
        </form>
      </div>
    </template>
  </section>
</template>

<script setup>
import { computed, reactive, ref, watch } from "vue";
import { toast } from "vue3-toastify";
import api from "../../plugins/axios";

const props = defineProps({
  caseId: { type: Number, required: true },
  // customer | vendor | admin (admin is view only)
  role: { type: String, required: true },
  // e.g. /customer/return-cases, /sc/order-requests, /admin/finance/refunds
  apiBase: { type: String, required: true },
});
const emit = defineEmits(["changed"]);

const RESOLUTIONS = [
  { value: "full_refund", label: "Full refund", hint: "The whole order amount back" },
  { value: "partial_refund", label: "Partial refund", hint: "Part of the amount back" },
  { value: "replacement", label: "Replacement", hint: "The shop sends it again" },
];
const STAGE = {
  awaiting_vendor: { title: "Waiting for the shop", customer: "The shop will accept, offer something else or decline.", vendor: "Accept what the customer asked, offer something else, or decline." },
  awaiting_customer: { title: "Waiting for the customer", customer: "The shop made an offer. Accept it or counter-offer.", vendor: "You made an offer. The customer will accept or counter-offer." },
  awaiting_return: { title: "Item on its way back", customer: "Send the item back to the shop.", vendor: "Confirm once the returned item reaches you." },
  awaiting_refund: { title: "Refund to be sent", customer: "The shop is sending your refund.", vendor: "Send the refund and record it here." },
  confirm_refund: { title: "Refund sent", customer: "Please confirm once you receive it.", vendor: "Waiting for the customer to confirm." },
  awaiting_replacement: { title: "Replacement to be sent", customer: "The shop is preparing your replacement.", vendor: "Send the replacement and mark it as sent." },
  confirm_replacement: { title: "Replacement sent", customer: "Please confirm once it arrives.", vendor: "Waiting for the customer to confirm." },
  completed: { title: "Settled", customer: "This is all done.", vendor: "This is all done." },
  rejected: { title: "Declined", customer: "The shop declined this request.", vendor: "You declined this request." },
  withdrawn: { title: "Withdrawn", customer: "You withdrew this request.", vendor: "The customer withdrew this request." },
};
const EVENTS = {
  offer: (m) => `offered ${describe(m.resolution, m.amount)}.`,
  accepted: (m) => `accepted ${describe(m.resolution, m.amount)}.`,
  rejected: () => "declined the request.",
  withdrawn: () => "withdrew the request.",
  item_received: () => "received the returned item.",
  refund_sent: (m) => `sent the ${money(m.amount)} refund.`,
  refund_confirmed: (m) => `confirmed receiving ${money(m.amount)}.`,
  refund_missing: () => "hasn't received the refund yet.",
  replacement_sent: () => "sent the replacement.",
  replacement_confirmed: () => "received the replacement.",
  replacement_missing: () => "hasn't received the replacement yet.",
};

const data = ref(null);
const loadError = ref("");
const busy = ref("");
const message = ref("");
const replacementNote = ref("");
const composer = reactive({ open: false, resolution: "partial_refund", amount: null, note: "" });
const closing = reactive({ open: false, reason: "" });
const refund = reactive({ method: "gcash", reference: "", note: "" });

const isOpen = computed(() => ["pending", "approved"].includes(data.value?.status));
const isClosedWithoutDeal = computed(() => ["rejected", "withdrawn"].includes(data.value?.status));
const canAct = computed(() => props.role !== "admin" && isOpen.value);
const refundable = computed(() => data.value?.order?.payment_status === "paid");
const stageHint = computed(() => {
  const stage = STAGE[data.value?.stage];
  if (!stage) return "";
  return props.role === "admin" ? stage.customer.replace(/^Please /, "The customer should ") : stage[props.role] ?? "";
});
const turnLabel = computed(() => {
  const next = data.value?.next_actor;
  if (!next) return STAGE[data.value?.stage]?.title ?? "";
  if (props.role === "admin") return next === "vendor" ? "Shop to act" : "Customer to act";
  return next === props.role ? "Your turn" : props.role === "vendor" ? "Waiting for customer" : "Waiting for the shop";
});
const chipTone = computed(() => {
  if (data.value?.stage === "completed") return "ok";
  if (isClosedWithoutDeal.value) return "off";
  return data.value?.next_actor === props.role ? "hot" : "calm";
});
const steps = computed(() => {
  const d = data.value;
  const outcome = d?.settled?.resolution ?? d?.offer?.resolution ?? d?.requested?.resolution;
  const list = [
    { key: "asked", label: "Requested" },
    { key: "agreed", label: "Agreed" },
  ];
  if (d?.type === "return") list.push({ key: "back", label: "Item returned" });
  list.push(outcome === "replacement" ? { key: "sent", label: "Replacement sent" } : { key: "sent", label: "Refund sent" });
  list.push({ key: "done", label: "Confirmed" });
  return list;
});
const stepIndex = computed(() => {
  const d = data.value;
  if (!d) return 0;
  if (d.stage === "completed") return steps.value.length;
  if (d.status === "pending") return 1;
  const ret = d.type === "return" ? 1 : 0;
  if (d.stage === "awaiting_return") return 2;
  if (["awaiting_refund", "awaiting_replacement"].includes(d.stage)) return 2 + ret;
  return 3 + ret; // sent, waiting for confirmation
});

const load = async () => {
  loadError.value = "";
  try {
    data.value = (await api.get(`${props.apiBase}/${props.caseId}`)).data.data;
  } catch (e) {
    loadError.value = e.response?.data?.message || "This request couldn't be loaded.";
  }
};
watch(() => props.caseId, () => {
  data.value = null;
  load();
}, { immediate: true });

const act = async (action, payload = {}) => {
  busy.value = action;
  try {
    const { data: res } = await api.post(`${props.apiBase}/${props.caseId}/${action}`, payload);
    data.value = res.data;
    toast.success(res.message || "Saved.");
    emit("changed", res.data);
    return true;
  } catch (e) {
    const errors = e.response?.data?.errors;
    toast.error(errors ? Object.values(errors)[0]?.[0] : e.response?.data?.message || "That didn't go through. Please try again.");
    return false;
  } finally {
    busy.value = "";
  }
};

const openOffer = () => {
  const current = data.value?.offer;
  Object.assign(composer, {
    open: true,
    resolution: refundable.value ? current?.resolution ?? "partial_refund" : "replacement",
    amount: current?.resolution === "partial_refund" ? current.amount : null,
    note: "",
  });
};
const sendOffer = async () => {
  const payload = { resolution: composer.resolution, note: composer.note || undefined };
  if (composer.resolution === "partial_refund") payload.amount = composer.amount;
  if (await act("offer", payload)) composer.open = false;
};
const closeCase = async () => {
  const ok = props.role === "vendor"
    ? await act("reject", { reason: closing.reason })
    : await act("withdraw", { note: closing.reason || undefined });
  if (ok) Object.assign(closing, { open: false, reason: "" });
};
const sendRefund = () => act("refund-sent", { method: refund.method, reference: refund.reference || undefined, note: refund.note || undefined });
const sendMessage = async () => {
  if (!message.value.trim() || busy.value) return;
  if (await act("message", { body: message.value.trim() })) message.value = "";
};

// ── Formatting ───────────────────────────────────────────────────
const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
function money(v) {
  return peso.format(Number(v || 0));
}
function describe(resolution, amount) {
  if (resolution === "full_refund") return `Full refund (${money(amount)})`;
  if (resolution === "partial_refund") return `Partial refund (${money(amount)})`;
  if (resolution === "replacement") return "Replacement";
  return "—";
}
const eventText = (m) => (EVENTS[m.kind] ? EVENTS[m.kind](m) : "updated the request.");
const isMine = (m) => m.author_role === props.role;
const fmtDate = (v) => (v ? new Date(v).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "");
const fmtDateTime = (v) =>
  v ? new Date(v).toLocaleString("en-PH", { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" }) : "";
</script>

<style scoped>
.rc {
  --rc-accent: var(--return-accent, #3f6f5a);
  --rc-accent-soft: var(--return-accent-soft, #e8f1ec);
  /* Vendor tokens, so the panel follows vendor dark mode (customer pages stay light) */
  --rc-ink: var(--v-text);
  --rc-muted: var(--v-muted);
  --rc-line: var(--v-border);
  display: flex;
  flex-direction: column;
  gap: 14px;
  font-size: 13.5px;
  color: var(--rc-ink);
}
.rc p {
  margin: 0;
}
.rc-skel {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.rc-sk {
  display: block;
  border-radius: 8px;
  background: linear-gradient(100deg, var(--v-subtle-2) 30%, var(--v-subtle) 50%, var(--v-subtle-2) 70%);
  background-size: 300% 100%;
  animation: rc-shimmer 1.6s ease-in-out infinite;
}
@keyframes rc-shimmer {
  from { background-position: 100% 0; }
  to { background-position: 0 0; }
}
.rc-error {
  color: var(--v-red-text);
}

/* Header */
.rc-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 12px;
}
.rc-eyebrow {
  font-size: 11.5px;
  color: var(--rc-muted);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.rc-title {
  margin: 2px 0;
  font-size: 17px;
  font-weight: 600;
}
.rc-sub {
  color: var(--rc-muted);
}
.rc-chip {
  flex-shrink: 0;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 11.5px;
  font-weight: 600;
  white-space: nowrap;
  background: var(--v-subtle);
  color: var(--v-text-2);
}
.rc-chip.hot { background: var(--v-amber-soft); color: var(--v-amber-text); }
.rc-chip.ok { background: var(--v-green-soft); color: var(--v-green-text); }
.rc-chip.off { background: var(--v-subtle); color: var(--v-muted); }

/* Steps */
.rc-steps {
  display: flex;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
  flex-wrap: wrap;
}
.rc-steps li {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 5px 10px 5px 5px;
  border-radius: 999px;
  background: var(--v-subtle);
  color: var(--rc-muted);
  font-size: 12px;
}
.rc-dot {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--v-surface);
  border: 1px solid var(--rc-line);
  font-size: 11px;
  font-weight: 700;
}
.rc-steps li.done {
  background: var(--rc-accent-soft);
  color: var(--rc-accent);
}
.rc-steps li.done .rc-dot {
  background: var(--rc-accent);
  border-color: var(--rc-accent);
  color: #fff;
}
.rc-steps li.now {
  background: var(--v-surface);
  color: var(--rc-ink);
  box-shadow: inset 0 0 0 1.5px var(--rc-accent);
  font-weight: 600;
}
.rc-closed {
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--v-subtle);
  color: var(--v-text-2);
}

/* Terms */
.rc-terms {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 10px;
}
.rc-terms > div {
  display: flex;
  flex-direction: column;
  gap: 2px;
  padding: 10px 12px;
  border: 1px solid var(--rc-line);
  border-radius: 10px;
}
.rc-lbl {
  margin: 0;
  font-size: 11px;
  font-weight: 600;
  color: var(--rc-muted);
  text-transform: uppercase;
  letter-spacing: 0.4px;
}
.rc-agreed {
  color: var(--rc-accent);
}

/* Boxes & forms */
.rc-box {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 14px;
  border-radius: 12px;
  background: var(--v-subtle);
  border: 1px solid var(--rc-line);
}
.rc-offer {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 15px;
}
.rc-actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
}
.rc-wait {
  color: var(--rc-muted);
  font-size: 12.5px;
}
.rc-btn {
  padding: 8px 14px;
  border-radius: 9px;
  border: 1px solid var(--rc-line);
  background: var(--v-surface);
  color: var(--rc-ink);
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
}
.rc-btn:hover:not(:disabled) {
  border-color: var(--rc-accent);
}
.rc-btn:disabled {
  opacity: 0.55;
  cursor: default;
}
.rc-btn--main {
  background: var(--rc-accent);
  border-color: var(--rc-accent);
  color: #fff;
}
.rc-btn--danger {
  color: var(--v-red-text);
}
.rc-btn--danger.rc-btn--main,
.rc-form--warn .rc-btn--danger {
  background: #dc2626;
  border-color: #dc2626;
  color: #fff;
}
.rc-btn--quiet {
  border-color: transparent;
  background: transparent;
  color: var(--rc-muted);
}
.rc-link {
  border: none;
  background: none;
  color: var(--rc-accent);
  font: inherit;
  text-decoration: underline;
  cursor: pointer;
}
.rc-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.rc-form--warn {
  padding: 12px;
  border-radius: 10px;
  background: var(--v-red-soft);
}
.rc-options {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 8px;
}
.rc-note {
  margin: 0;
  font-size: 12px;
  color: var(--v-muted);
}
.rc-option {
  display: flex;
  gap: 8px;
  padding: 10px;
  border: 1px solid var(--rc-line);
  border-radius: 10px;
  background: var(--v-surface);
  cursor: pointer;
}
.rc-option.on {
  border-color: var(--rc-accent);
  box-shadow: 0 0 0 2px var(--rc-accent-soft);
}
.rc-option.off {
  opacity: 0.45;
  cursor: not-allowed;
}
.rc-option input {
  accent-color: var(--rc-accent);
}
.rc-option span {
  display: flex;
  flex-direction: column;
}
.rc-option small {
  color: var(--rc-muted);
  font-size: 11.5px;
}
.rc-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 10px;
}
.rc-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
  font-size: 12px;
  font-weight: 500;
  color: var(--v-text-2);
}
.rc-input {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid var(--v-border-strong);
  border-radius: 9px;
  background: var(--v-input);
  font: inherit;
  font-size: 13px;
  color: var(--rc-ink);
  box-sizing: border-box;
  resize: vertical;
}
.rc-input:focus {
  outline: none;
  border-color: var(--rc-accent);
  box-shadow: 0 0 0 3px var(--rc-accent-soft);
}
.rc-done {
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--v-green-soft);
  color: var(--v-green-text);
  font-weight: 500;
}

/* Evidence */
.rc-media {
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
}
.rc-media img,
.rc-media video {
  max-width: 240px;
  max-height: 180px;
  border-radius: 10px;
  object-fit: cover;
  border: 1px solid var(--rc-line);
}
.rc-media figcaption {
  font-size: 11.5px;
  color: var(--rc-muted);
}

/* Thread */
.rc-thread {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.rc-thread ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 8px;
  max-height: 360px;
  overflow-y: auto;
}
.rc-thread li {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 3px;
}
.rc-thread li.mine:not(.event) {
  align-items: flex-end;
}
.rc-who {
  font-size: 11px;
  color: var(--rc-muted);
}
.rc-bubble {
  max-width: 85%;
  padding: 8px 12px;
  border-radius: 12px;
  background: var(--v-subtle);
  white-space: pre-line;
  overflow-wrap: anywhere;
}
.rc-thread li.mine .rc-bubble {
  background: var(--rc-accent-soft);
}
.rc-attach {
  font-size: 12px;
  color: var(--rc-accent);
}
.rc-event {
  width: 100%;
  padding: 6px 10px;
  border-left: 3px solid var(--rc-line);
  color: var(--v-text-2);
  font-size: 12.5px;
}
.rc-event-note {
  display: block;
  color: var(--rc-muted);
  font-style: italic;
}
.rc-event-time {
  display: block;
  font-size: 11px;
  color: var(--rc-muted);
}
.rc-compose {
  display: flex;
  gap: 8px;
  align-items: flex-end;
}
@media (prefers-reduced-motion: reduce) {
  .rc-sk {
    animation: none;
  }
}
</style>
