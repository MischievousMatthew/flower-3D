<template>
  <HrPage :title="o ? o.order_number : 'Purchase order'" :subtitle="o ? subtitle : 'Loading the order…'">
    <template #actions>
      <router-link :to="{ name: 'OrderList' }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.chevronLeft"></span>All orders</router-link>
      <button v-if="o" type="button" class="sp-btn sp-btn-ghost" @click="printOrder"><span v-html="ICONS.print"></span>Print</button>
    </template>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Stages -->
    <section class="sp-card od-steps" aria-label="Stage">
      <template v-if="!o"><div class="bc-skeleton sp-sk-line w70"></div></template>
      <ol v-else class="od-stepper">
        <li v-for="(s, i) in PO_STAGES" :key="s.key" :class="{ done: i < stageIndex, now: i === stageIndex }" :aria-current="i === stageIndex ? 'step' : undefined">
          <span class="od-step-dot"><span v-if="i < stageIndex" v-html="ICONS.check"></span><template v-else>{{ i + 1 }}</template></span>
          <span class="od-step-text"><b>{{ s.label }}</b><small>{{ stepNote(s.key) }}</small></span>
        </li>
      </ol>
    </section>

    <!-- What to do next -->
    <section v-if="o && nextAction" class="sp-card od-next" :class="nextAction.tone" aria-label="Next step">
      <span class="od-next-ic" v-html="ICONS[nextAction.icon]"></span>
      <div class="grow">
        <b>{{ nextAction.title }}</b>
        <span>{{ nextAction.text }}</span>
      </div>
      <div class="od-next-actions">
        <template v-if="o.status === 'draft'">
          <button v-if="can('sc_orders', 'delete')" type="button" class="sp-btn sp-btn-ghost" @click="confirmDelete = true">Delete</button>
          <router-link v-if="can('sc_orders', 'create')" :to="{ name: 'OrderCreate', query: { order: o.id } }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.edit"></span>Edit</router-link>
          <button v-if="can('sc_orders', 'create') && !canApprove" type="button" class="sp-btn sp-btn-primary" :disabled="busy" @click="submit">Send for approval</button>
          <button v-if="canApprove" type="button" class="sp-btn sp-btn-primary" :disabled="busy" @click="openApprove">Approve &amp; send</button>
        </template>
        <template v-else-if="o.status === 'pending_approval'">
          <router-link v-if="can('sc_orders', 'create')" :to="{ name: 'OrderCreate', query: { order: o.id } }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.edit"></span>Edit</router-link>
          <button v-if="canApprove" type="button" class="sp-btn sp-btn-ghost" @click="openSendBack">Send back</button>
          <button v-if="canApprove" type="button" class="sp-btn sp-btn-primary" :disabled="busy" @click="openApprove">Approve &amp; send</button>
        </template>
        <template v-else-if="isReceivable(o.status)">
          <button v-if="canEdit('sc_orders') && o.supplier?.email" type="button" class="sp-btn sp-btn-ghost" :disabled="busy" @click="emailAgain"><span v-html="ICONS.send"></span>{{ o.sent_at ? "Email again" : "Email supplier" }}</button>
          <button v-if="canEdit('sc_orders') && NEXT_STEPS[o.status]" type="button" class="sp-btn sp-btn-ghost" @click="stepModal = { next: NEXT_STEPS[o.status][0].status, busy: false }">Update progress</button>
          <router-link v-if="canReceive" :to="{ name: 'BatchesToReceive', query: { order: o.id } }" class="sp-btn sp-btn-primary"><span v-html="ICONS.box"></span>Receive &amp; inspect</router-link>
        </template>
      </div>
    </section>

    <div v-if="!o" class="od-grid">
      <div class="sp-card"><div class="bc-skeleton sp-sk-block"></div></div>
      <div class="sp-card"><div class="bc-skeleton sp-sk-block"></div></div>
    </div>

    <div v-else class="od-grid">
      <div class="od-col">
        <!-- Items -->
        <section class="sp-card" aria-labelledby="od-items">
          <div class="sp-card-head">
            <div><h2 id="od-items">Items</h2><p>{{ o.items.length }} {{ o.items.length === 1 ? "line" : "lines" }} · {{ units.toLocaleString("en-PH") }} units<template v-if="received.done"> · {{ received.done.toLocaleString("en-PH") }} received</template></p></div>
          </div>
          <div class="od-table-wrap">
            <table class="od-table">
              <thead>
                <tr><th scope="col">Item</th><th scope="col" class="num">Ordered</th><th v-if="showReceived" scope="col" class="num">Received</th><th v-if="showRejected" scope="col" class="num">Rejected</th><th scope="col" class="num">Agreed price</th><th scope="col" class="num">Amount</th></tr>
              </thead>
              <tbody>
                <tr v-for="it in o.items" :key="it.id">
                  <td><b>{{ it.product_name }}</b><small v-if="!it.product_id" class="od-warn">Not linked to a stock item</small></td>
                  <td class="num">{{ Number(it.quantity).toLocaleString("en-PH") }} <small>{{ it.unit || "" }}</small></td>
                  <td v-if="showReceived" class="num">
                    <span :class="{ full: it.received_qty >= it.quantity }">{{ Number(it.received_qty ?? 0).toLocaleString("en-PH") }}</span>
                    <div class="od-mini" role="img" :aria-label="`${it.received_qty ?? 0} of ${it.quantity} received`"><span :style="{ width: `${Math.min(100, ((it.received_qty ?? 0) / it.quantity) * 100)}%` }"></span></div>
                  </td>
                  <td v-if="showRejected" class="num"><span :class="{ bad: it.rejected_qty }">{{ it.rejected_qty || 0 }}</span><small v-if="it.cancelled_qty" class="od-closed">{{ it.cancelled_qty }} not coming</small></td>
                  <td class="num">{{ money(it.price) }}<small v-if="it.unit"> / {{ it.unit }}</small></td>
                  <td class="num"><b>{{ money(it.quantity * it.price) }}</b></td>
                </tr>
              </tbody>
              <tfoot>
                <tr><td :colspan="3 + (showReceived ? 1 : 0) + (showRejected ? 1 : 0)" class="num">Total</td><td class="num"><b class="od-total">{{ money(o.total_amount) }}</b></td></tr>
              </tfoot>
            </table>
          </div>
          <p v-if="o.notes" class="od-notes">{{ o.notes }}</p>
        </section>

        <!-- Deliveries -->
        <section v-if="(o.receipts ?? []).length" class="sp-card" aria-labelledby="od-deliveries">
          <div class="sp-card-head"><div><h2 id="od-deliveries">Deliveries</h2><p>Each delivery as it was counted and inspected</p></div></div>
          <ul class="od-dl">
            <li v-for="r in o.receipts" :key="r.id">
              <button type="button" class="od-dl-head" :aria-expanded="openReceipt === r.id" @click="openReceipt = openReceipt === r.id ? null : r.id">
                <span class="grow"><b>{{ r.receipt_number }}</b><small>{{ poDate(r.received_date) }} · {{ r.received_by_name || "Someone on the team" }}<template v-if="r.delivery_ref"> · {{ r.delivery_ref }}</template></small></span>
                <span class="od-dl-nums">
                  <span class="sp-chip green">{{ r.accepted_total }} in</span>
                  <span v-if="r.rejected_total" class="sp-chip red">{{ r.rejected_total }} rejected</span>
                  <span v-if="r.missing_total" class="sp-chip amber">{{ r.missing_total }} missing</span>
                </span>
              </button>
              <ReceiptLines v-if="openReceipt === r.id" :receipt="r" class="od-dl-body" />
            </li>
          </ul>
        </section>

        <!-- History -->
        <section class="sp-card" aria-labelledby="od-history">
          <div class="sp-card-head"><div><h2 id="od-history">History</h2><p>Everything that happened to this order</p></div></div>
          <p v-if="!(o.events ?? []).length" class="sp-hint">No history yet.</p>
          <ol v-else class="od-timeline">
            <li v-for="e in o.events" :key="e.id" :class="`t-${EVENT_TONE[e.event] ?? 'slate'}`">
              <span class="od-tl-dot"></span>
              <div class="grow">
                <b>{{ eventText(e) }}</b>
                <span v-if="e.note" class="od-tl-note">{{ e.event === "emailed" || e.event === "email_failed" ? `To ${e.note}` : e.note }}</span>
                <small>{{ e.actor_name || "Automatic" }} · {{ poDateTime(e.created_at) }}</small>
              </div>
            </li>
          </ol>
        </section>
      </div>

      <div class="od-col">
        <!-- Supplier -->
        <section class="sp-card" aria-labelledby="od-sup">
          <div class="sp-card-head">
            <div><h2 id="od-sup">Supplier</h2></div>
            <router-link v-if="o.supplier && canView('suppliers')" :to="{ name: 'SupplierProfile', params: { id: o.supplier.id } }" class="sp-btn sp-btn-ghost sp-btn-sm">Profile</router-link>
          </div>
          <div v-if="o.supplier" class="od-sup">
            <span class="od-logo">{{ initials(o.supplier.company_name) }}</span>
            <div class="grow">
              <b>{{ o.supplier.company_name }}</b>
              <span>{{ o.supplier.contact_person }}</span>
              <a v-if="o.supplier.phone" :href="`tel:${o.supplier.phone}`">{{ o.supplier.phone }}</a>
              <a v-if="o.supplier.email" :href="`mailto:${o.supplier.email}`">{{ o.supplier.email }}</a>
            </div>
          </div>
          <p v-else class="sp-hint">This supplier was removed.</p>
        </section>

        <!-- Dates -->
        <section class="sp-card" aria-labelledby="od-dates">
          <div class="sp-card-head"><div><h2 id="od-dates">Dates</h2></div></div>
          <dl class="od-facts">
            <div><dt>Ordered</dt><dd>{{ poDate(o.order_date || o.created_at) }}</dd></div>
            <div><dt>Expected</dt><dd :class="{ late: late }">{{ o.expected_date ? poDate(o.expected_date) : "Not set" }}<small v-if="dueText(o.expected_date, o.status)">{{ dueText(o.expected_date, o.status) }}</small></dd></div>
            <div v-if="o.completed_at"><dt>Completed</dt><dd>{{ poDate(o.completed_at) }}</dd></div>
            <div v-if="o.shipment?.shipped_date"><dt>Shipped</dt><dd>{{ poDate(o.shipment.shipped_date) }}<small v-if="o.shipment.carrier">{{ o.shipment.carrier }}{{ o.shipment.tracking_number ? ` · #${o.shipment.tracking_number}` : "" }}</small></dd></div>
          </dl>
        </section>

        <!-- People -->
        <section class="sp-card" aria-labelledby="od-people">
          <div class="sp-card-head"><div><h2 id="od-people">Who</h2></div></div>
          <dl class="od-facts">
            <div><dt>Created by</dt><dd>{{ o.created_by_name || "—" }}</dd></div>
            <div><dt>Approved by</dt><dd>{{ o.approved_by_name || (isEditable(o.status) ? "Not yet" : "—") }}<small v-if="o.approved_at">{{ poDateTime(o.approved_at) }}</small></dd></div>
            <div><dt>Sent to supplier</dt><dd>{{ o.sent_at ? poDateTime(o.sent_at) : isEditable(o.status) ? "Not yet" : "Not emailed" }}<small v-if="o.sent_to_email">{{ o.sent_to_email }}</small></dd></div>
          </dl>
        </section>
      </div>
    </div>

    <!-- Approve -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="approveModal" class="sp-backdrop" @click="approveModal = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="approveModal" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="od-ap-title" @submit.prevent="approve">
            <div class="sp-modal-head">
              <div><h3 id="od-ap-title">Approve {{ o.order_number }}?</h3><p>{{ money(o.total_amount) }} to {{ o.supplier?.company_name }}. After this it can't be changed.</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="approveModal = null"><span v-html="ICONS.close"></span></button>
            </div>
            <label v-if="o.supplier?.email" class="od-check">
              <input v-model="approveModal.email" type="checkbox" />
              <span>Email the order to {{ o.supplier.email }}</span>
            </label>
            <p v-else class="sp-hint">{{ o.supplier?.company_name }} has no email address, so print the order or send it yourself.</p>
            <label class="sp-field">
              <span class="sp-label">Note <small>(optional)</small></span>
              <input v-model.trim="approveModal.note" class="sp-input" maxlength="500" placeholder="e.g. Approved within the October budget" />
            </label>
            <p v-if="approveModal.error" class="sp-error">{{ approveModal.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="approveModal = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="approveModal.busy">{{ approveModal.busy ? "Approving…" : "Approve & send" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Send back -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="sendBackModal" class="sp-backdrop" @click="sendBackModal = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="sendBackModal" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="od-sb-title" novalidate @submit.prevent="sendBack">
            <div class="sp-modal-head">
              <div><h3 id="od-sb-title">Send it back to draft</h3><p>{{ o.created_by_name || "Whoever wrote it" }} sees your reason and can fix the order.</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="sendBackModal = null"><span v-html="ICONS.close"></span></button>
            </div>
            <label class="sp-field">
              <span class="sp-label">What needs to change</span>
              <textarea v-model.trim="sendBackModal.reason" class="sp-textarea" :class="{ invalid: sendBackModal.error }" rows="3" maxlength="500" placeholder="e.g. Order 150 roses instead, and ask for delivery on Friday"></textarea>
              <small v-if="sendBackModal.error" class="sp-error">{{ sendBackModal.error }}</small>
            </label>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="sendBackModal = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="sendBackModal.busy">{{ sendBackModal.busy ? "Sending…" : "Send back" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Progress (the existing steps: preparing, on the way, arrived) -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="stepModal" class="sp-backdrop" @click="stepModal = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="stepModal" class="sp-modal-wrap">
          <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="od-st-title" @submit.prevent="applyStep">
            <div class="sp-modal-head">
              <div><h3 id="od-st-title">Update progress</h3><p>Right now: {{ statusText(o.status) }}.</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="stepModal = null"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="od-steps-pick" role="radiogroup">
              <label v-for="s in NEXT_STEPS[o.status] ?? []" :key="s.status" class="od-pick" :class="{ on: stepModal.next === s.status }">
                <input v-model="stepModal.next" type="radio" name="od-step" :value="s.status" />{{ s.label }}
              </label>
            </div>
            <p v-if="stepModal.error" class="sp-error">{{ stepModal.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="stepModal = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="stepModal.busy">{{ stepModal.busy ? "Saving…" : "Save" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>

    <!-- Delete a draft -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="confirmDelete" class="sp-backdrop" @click="confirmDelete = false"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="confirmDelete" class="sp-modal-wrap">
          <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="od-del-title">
            <div class="sp-modal-head">
              <div><h3 id="od-del-title">Delete this draft?</h3><p>It was never sent, so nothing else changes. If it came from a funding request, that request can be ordered again.</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="confirmDelete = false"><span v-html="ICONS.close"></span></button>
            </div>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="confirmDelete = false">Keep it</button>
              <button type="button" class="sp-btn sp-btn-danger" :disabled="busy" @click="remove">Delete draft</button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import HrPage from "../../../../../components/hr/HrPage.vue";
import ReceiptLines from "../../../../../components/procurement/ReceiptLines.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { money } from "../../../../../components/vendor/finance/financeUi";
import { initials } from "../../../../../components/procurement/supplierUi";
import { PO_STAGES, NEXT_STEPS, EVENT_TONE, stageOf, statusText, eventText, isEditable, isReceivable, poDate, poDateTime, dueText, daysUntil } from "../../../../../components/procurement/poUi";
import { useAssignment } from "../../../../../composables/useAssignment";
import { orderService } from "../../../../../services/orderService";
import { toast } from "@/utils/snackbar";
import "../../../../../components/vendor/finance/finance.css";

const props = defineProps({ id: { type: [String, Number], default: null } });
const router = useRouter();
const { can, canEdit, canView } = useAssignment();
const canApprove = computed(() => can("sc_orders", "approve"));

const o = ref(null);
const loadError = ref("");
const busy = ref(false);
const approveModal = ref(null);
const sendBackModal = ref(null);
const stepModal = ref(null);
const confirmDelete = ref(false);
const openReceipt = ref(null);
// Warehouse staff (create) or purchasing staff (edit) can receive
const canReceive = computed(() => can("warehouse", "create") || canEdit("sc_orders"));
const showRejected = computed(() => (o.value?.items ?? []).some((i) => i.rejected_qty || i.cancelled_qty));

const stageIndex = computed(() => PO_STAGES.findIndex((s) => s.key === stageOf(o.value?.status).key));
const units = computed(() => (o.value?.items ?? []).reduce((s, i) => s + Number(i.quantity), 0));
const received = computed(() => ({ done: (o.value?.items ?? []).reduce((s, i) => s + Number(i.received_qty ?? 0), 0) }));
const showReceived = computed(() => ["partially_received", "completed"].includes(o.value?.status) || received.value.done > 0);
const late = computed(() => isReceivable(o.value?.status) && daysUntil(o.value?.expected_date) < 0);
const subtitle = computed(() => [o.value.supplier?.company_name, `${money(o.value.total_amount)}`, statusText(o.value.status)].filter(Boolean).join(" · "));
const lastSentBack = computed(() => (o.value?.events ?? []).find((e) => e.event === "sent_back"));

function stepNote(key) {
  const x = o.value;
  if (key === "draft") return x.created_by_name ? `by ${x.created_by_name}` : "";
  if (key === "pending_approval") return x.submitted_at && x.status !== "draft" ? poDate(x.submitted_at) : "";
  if (key === "ordered") return x.approved_at ? poDate(x.approved_at) : "";
  if (key === "partially_received") return received.value.done && x.status !== "completed" ? `${Math.round((received.value.done / units.value) * 100)}% in` : "";
  if (key === "completed") return x.completed_at ? poDate(x.completed_at) : "";
  return "";
}

const nextAction = computed(() => {
  const x = o.value;
  switch (x.status) {
    case "draft":
      return lastSentBack.value
        ? { tone: "amber", icon: "restore", title: "Sent back to you", text: `${lastSentBack.value.actor_name || "The approver"}: “${lastSentBack.value.note}” Make the change, then send it for approval again.` }
        : { tone: "slate", icon: "edit", title: "This is a draft", text: canApprove.value ? "Finish it, then approve it to send it to the supplier." : "Finish it, then send it for approval." };
    case "pending_approval":
      return { tone: "amber", icon: "clock", title: "Waiting for approval", text: canApprove.value ? "Check the items and prices. Approving sends it to the supplier." : "Someone who can approve orders needs to check it. You can still change it until then." };
    case "partially_received":
      return { tone: "violet", icon: "box", title: "Part of it has arrived", text: `${received.value.done.toLocaleString("en-PH")} of ${units.value.toLocaleString("en-PH")} units are in stock. Receive the rest when it comes.` };
    case "completed":
      return null;
    default:
      return late.value
        ? { tone: "red", icon: "alert", title: `${dueText(x.expected_date, x.status)}`, text: `It was expected ${poDate(x.expected_date)}. Contact ${x.supplier?.company_name ?? "the supplier"}, or receive it if it came.` }
        : { tone: "blue", icon: "truck", title: statusText(x.status), text: x.expected_date ? `Expected ${poDate(x.expected_date)}. Receive the items when they arrive.` : "Receive the items when they arrive." };
  }
});

async function load() {
  loadError.value = "";
  try {
    const res = await orderService.find(props.id);
    o.value = res.data;
  } catch (e) {
    loadError.value = apiMessage(e, "Couldn't load this order.");
  }
}

async function submit() {
  busy.value = true;
  try {
    await orderService.submit(o.value.id);
    toast.success("Sent for approval.");
    await load();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't send it for approval."));
  } finally {
    busy.value = false;
  }
}

function openApprove() {
  approveModal.value = { email: !!o.value.supplier?.email, note: "", busy: false, error: "" };
}
async function approve() {
  const m = approveModal.value;
  m.busy = true;
  m.error = "";
  try {
    const res = await orderService.approve(o.value.id, { send_email: m.email, note: m.note || null });
    approveModal.value = null;
    toast.success(res.data.emailed ? `Approved and emailed to ${o.value.supplier.email}.` : res.data.emailed === false ? "Approved. The email didn't go through; try Email again." : "Approved. It's with the supplier.");
    await load();
  } catch (e) {
    m.busy = false;
    m.error = apiMessage(e, "Couldn't approve the order.");
  }
}

function openSendBack() {
  sendBackModal.value = { reason: "", busy: false, error: "" };
}
async function sendBack() {
  const m = sendBackModal.value;
  if (!m.reason) {
    m.error = "Say what needs to change.";
    return;
  }
  m.busy = true;
  try {
    await orderService.sendBack(o.value.id, m.reason);
    sendBackModal.value = null;
    toast.success("Sent back to draft.");
    await load();
  } catch (e) {
    m.busy = false;
    m.error = apiMessage(e, "Couldn't send it back.");
  }
}

async function emailAgain() {
  busy.value = true;
  try {
    const res = await orderService.email(o.value.id);
    toast.success(res.data.message);
    await load();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't email the supplier."));
  } finally {
    busy.value = false;
  }
}

async function applyStep() {
  const m = stepModal.value;
  m.busy = true;
  m.error = "";
  try {
    await orderService.updateStatus(o.value.id, m.next);
    stepModal.value = null;
    toast.success("Progress updated.");
    await load();
  } catch (e) {
    m.busy = false;
    m.error = apiMessage(e, "Couldn't update the progress.");
  }
}

async function remove() {
  busy.value = true;
  try {
    await orderService.remove(o.value.id);
    toast.success(`${o.value.order_number} was deleted.`);
    router.push({ name: "OrderList" });
  } catch (e) {
    confirmDelete.value = false;
    toast.error(apiMessage(e, "Couldn't delete the order."));
  } finally {
    busy.value = false;
  }
}

// A clean page to print or save as PDF
function printOrder() {
  const x = o.value;
  const esc = (s) => String(s ?? "").replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" })[c]);
  const rows = x.items.map((i) => `<tr><td>${esc(i.product_name)}</td><td class="n">${Number(i.quantity).toLocaleString("en-PH")} ${esc(i.unit || "")}</td><td class="n">${esc(money(i.price))}</td><td class="n">${esc(money(i.quantity * i.price))}</td></tr>`).join("");
  const w = window.open("", "_blank", "width=820,height=900");
  if (!w) {
    toast.error("Your browser blocked the print window. Allow pop-ups for this site and try again.");
    return;
  }
  w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(x.order_number)}</title><style>
    body{font-family:Arial,sans-serif;color:#1f2430;margin:40px;font-size:14px}h1{margin:0 0 4px;font-size:24px}.muted{color:#5b6472}
    .grid{display:flex;gap:40px;margin:24px 0}.grid div{flex:1}.lbl{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#5b6472;margin-bottom:4px}
    table{width:100%;border-collapse:collapse;margin-top:12px}th,td{padding:9px 6px;border-bottom:1px solid #e3e8e5;text-align:left}th{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#5b6472}
    .n{text-align:right}tfoot td{font-weight:700;border:0;font-size:16px}.notes{margin-top:20px;padding:12px;border-left:3px solid #4DB173;background:#f3f7f5;white-space:pre-line}
  </style></head><body>
    <h1>Purchase order ${esc(x.order_number)}</h1><div class="muted">${esc(statusText(x.status))}</div>
    <div class="grid"><div><div class="lbl">Supplier</div><b>${esc(x.supplier?.company_name)}</b><br>${esc(x.supplier?.contact_person)}<br>${esc(x.supplier?.phone)}<br>${esc(x.supplier?.email)}<br>${esc(x.supplier?.address)}</div>
    <div><div class="lbl">Order date</div>${esc(poDate(x.order_date || x.created_at))}<br><br><div class="lbl">Deliver by</div>${esc(x.expected_date ? poDate(x.expected_date) : "Not set")}</div></div>
    <table><thead><tr><th>Item</th><th class="n">Quantity</th><th class="n">Agreed price</th><th class="n">Amount</th></tr></thead><tbody>${rows}</tbody>
    <tfoot><tr><td colspan="3" class="n">Total</td><td class="n">${esc(money(x.total_amount))}</td></tr></tfoot></table>
    ${x.notes ? `<div class="notes">${esc(x.notes)}</div>` : ""}
    <p class="muted" style="margin-top:28px">Approved by ${esc(x.approved_by_name || "—")}</p>
    <script>window.onload=function(){window.print()}<\/script></body></html>`);
  w.document.close();
}

onMounted(load);
</script>

<style scoped>
.grow {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.sr {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
}

/* Stepper */
.od-steps {
  margin-bottom: 14px;
  padding: 16px 18px;
}
.od-stepper {
  list-style: none;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 8px;
  margin: 0;
  padding: 0;
}
.od-stepper li {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}
.od-stepper li + li::before {
  content: "";
  position: absolute;
  top: 15px;
  right: calc(100% + 2px);
  width: 6px;
  height: 2px;
  background: var(--v-border-strong);
}
.od-step-dot {
  flex: none;
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  border: 2px solid var(--v-border-strong);
  border-radius: 50%;
  color: var(--v-muted);
  font-size: 12px;
  font-weight: 700;
}
.od-step-dot :deep(svg) {
  width: 15px;
  height: 15px;
}
.od-stepper li.done .od-step-dot {
  border-color: var(--bc-accent);
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
}
.od-stepper li.now .od-step-dot {
  border-color: var(--bc-accent);
  color: var(--bc-accent-deep);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--bc-accent) 18%, transparent);
}
.od-step-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.od-step-text b {
  overflow: hidden;
  font-size: 13px;
  font-weight: 600;
  color: var(--v-muted);
  text-overflow: ellipsis;
  white-space: nowrap;
}
.od-stepper li.done b,
.od-stepper li.now b {
  color: var(--v-text);
}
.od-step-text small {
  font-size: 11.5px;
  color: var(--v-faint);
}

/* Next step */
.od-next {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 14px;
  margin-bottom: 16px;
  border-left: 4px solid var(--v-border-strong);
}
.od-next.amber { border-left-color: #f59e0b; }
.od-next.blue { border-left-color: #3b82f6; }
.od-next.violet { border-left-color: #8b5cf6; }
.od-next.red { border-left-color: var(--v-red-text); }
.od-next-ic {
  flex: none;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--v-subtle);
  color: var(--v-text-2);
}
.od-next.amber .od-next-ic { background: var(--v-amber-soft); color: var(--v-amber-text); }
.od-next.blue .od-next-ic { background: var(--v-blue-soft); color: var(--v-blue-text); }
.od-next.violet .od-next-ic { background: var(--v-purple-soft); color: var(--v-purple-text); }
.od-next.red .od-next-ic { background: var(--v-red-soft); color: var(--v-red-text); }
.od-next-ic :deep(svg) {
  width: 19px;
  height: 19px;
}
.od-next .grow {
  min-width: 220px;
}
.od-next b {
  font-size: 14.5px;
  color: var(--v-text);
}
.od-next .grow > span {
  font-size: 13px;
  color: var(--v-text-2);
}
.od-next-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

/* Layout */
.od-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.7fr) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}
.od-col {
  display: flex;
  flex-direction: column;
  gap: 16px;
  min-width: 0;
}

/* Items */
.od-table-wrap {
  overflow-x: auto;
}
.od-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.od-table th {
  padding: 10px;
  border-bottom: 1px solid var(--v-border);
  color: var(--v-muted);
  font-size: 11.5px;
  font-weight: 600;
  text-align: left;
  white-space: nowrap;
}
.od-table td {
  padding: 12px 10px;
  border-bottom: 1px solid var(--v-border);
  color: var(--v-text-2);
}
.od-table td b {
  color: var(--v-text);
  font-weight: 600;
}
.od-table td small {
  font-size: 11.5px;
  color: var(--v-faint);
}
.od-table tfoot td {
  border-bottom: 0;
  font-weight: 600;
  color: var(--v-text);
}
.od-total {
  font-size: 17px;
}
.num {
  text-align: right !important;
  white-space: nowrap;
}
.od-warn {
  display: block;
  color: var(--v-amber-text) !important;
}
.full {
  color: var(--v-green-text);
  font-weight: 600;
}
.od-mini {
  width: 70px;
  height: 4px;
  margin: 4px 0 0 auto;
  border-radius: 999px;
  overflow: hidden;
  background: var(--v-subtle-2);
}
.od-mini span {
  display: block;
  height: 100%;
  background: #8b5cf6;
}
.od-notes {
  margin: 14px 0 0;
  padding: 12px 14px;
  border-left: 3px solid var(--bc-accent);
  border-radius: 0 12px 12px 0;
  background: var(--v-subtle);
  font-size: 13px;
  line-height: 1.55;
  color: var(--v-text-2);
  white-space: pre-line;
}

/* History */
.od-timeline {
  list-style: none;
  margin: 0;
  padding: 0;
}
.od-timeline li {
  position: relative;
  display: flex;
  gap: 12px;
  padding: 0 0 16px;
}
.od-timeline li:not(:last-child)::after {
  content: "";
  position: absolute;
  top: 14px;
  bottom: 0;
  left: 5px;
  width: 2px;
  background: var(--v-border);
}
.od-tl-dot {
  position: relative;
  z-index: 1;
  flex: none;
  width: 12px;
  height: 12px;
  margin-top: 3px;
  border: 2px solid var(--v-surface);
  border-radius: 50%;
  background: var(--v-faint);
  box-shadow: 0 0 0 1px var(--v-border-strong);
}
.t-green .od-tl-dot { background: #10b981; }
.t-blue .od-tl-dot { background: #3b82f6; }
.t-amber .od-tl-dot { background: #f59e0b; }
.t-violet .od-tl-dot { background: #8b5cf6; }
.t-red .od-tl-dot { background: var(--v-red-text); }
.od-timeline b {
  font-size: 13px;
  font-weight: 600;
  color: var(--v-text);
}
.od-tl-note {
  font-size: 12.5px;
  color: var(--v-text-2);
}
.od-timeline small {
  font-size: 11.5px;
  color: var(--v-faint);
}

/* Side */
.od-sup {
  display: flex;
  gap: 12px;
}
.od-sup b {
  font-size: 14px;
  color: var(--v-text);
}
.od-sup span,
.od-sup a {
  font-size: 12.5px;
  color: var(--v-text-2);
}
.od-sup a:hover {
  color: var(--bc-accent-deep);
}
.od-logo {
  flex: none;
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: var(--bc-accent-soft);
  color: var(--bc-accent-deep);
  font-size: 13px;
  font-weight: 600;
}
.od-facts {
  display: grid;
  gap: 10px;
  margin: 0;
}
.od-facts div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 13px;
}
.od-facts dt {
  color: var(--v-muted);
}
.od-facts dd {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  margin: 0;
  font-weight: 600;
  color: var(--v-text);
  text-align: right;
}
.od-facts dd small {
  font-size: 11.5px;
  font-weight: 500;
  color: var(--v-faint);
}
.od-facts dd.late,
.od-facts dd.late small {
  color: var(--v-red-text);
}
.bad {
  color: var(--v-red-text);
  font-weight: 600;
}
.od-closed {
  display: block;
  color: var(--v-amber-text) !important;
}
.od-dl {
  list-style: none;
  margin: 0;
  padding: 0;
}
.od-dl > li + li {
  border-top: 1px solid var(--v-border);
}
.od-dl-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 10px 4px;
  border: 0;
  border-radius: 10px;
  background: none;
  color: inherit;
  font: inherit;
  text-align: left;
  cursor: pointer;
}
.od-dl-head:hover,
.od-dl-head:focus-visible {
  background: var(--v-subtle);
  outline: none;
}
.od-dl-head b {
  font-size: 13.5px;
  color: var(--v-text);
}
.od-dl-head small {
  font-size: 12px;
  color: var(--v-muted);
}
.od-dl-nums {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
.od-dl-body {
  padding: 0 4px 12px;
}
.od-batch-link {
  display: inline-block;
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  text-decoration: underline;
  cursor: pointer;
  margin-top: 14px;
  font-size: 12.5px;
  color: var(--bc-accent-deep);
}

/* Modals */
.od-check {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin-bottom: 14px;
  font-size: 13px;
  color: var(--v-text);
  cursor: pointer;
}
.od-check input {
  width: 18px;
  height: 18px;
  accent-color: var(--bc-accent);
}
.od-steps-pick {
  display: grid;
  gap: 8px;
  margin-bottom: 12px;
}
.od-pick {
  position: relative;
  display: flex;
  align-items: center;
  min-height: 44px;
  padding: 0 14px;
  border: 1.5px solid var(--v-border);
  border-radius: 12px;
  font-size: 13.5px;
  color: var(--v-text);
  cursor: pointer;
}
.od-pick.on {
  border-color: var(--bc-accent);
  background: color-mix(in srgb, var(--bc-accent) 7%, transparent);
}
.od-pick input {
  position: absolute;
  opacity: 0;
}
.od-pick:focus-within {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
}

@media (max-width: 1000px) {
  .od-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 760px) {
  .od-stepper {
    grid-template-columns: minmax(0, 1fr);
    gap: 10px;
  }
  .od-stepper li + li::before {
    display: none;
  }
}
</style>
