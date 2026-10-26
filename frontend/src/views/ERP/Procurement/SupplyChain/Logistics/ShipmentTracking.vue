<template>
  <HrPage :title="s ? s.tracking_number : 'Shipment'" :subtitle="s ? `${s.carrier} · ${shipStatus(s.status).label}` : 'Loading the shipment…'">
    <template #actions>
      <router-link :to="{ name: 'ShipmentList' }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.chevronLeft"></span>All shipments</router-link>
      <template v-if="s && canEdit('deliveries')">
        <button v-if="s.status === 'pending'" type="button" class="sp-btn sp-btn-primary" :disabled="busy" @click="act('ship')"><span v-html="ICONS.truck"></span>Mark as shipped</button>
        <button v-if="['in_transit', 'out_for_delivery'].includes(s.status)" type="button" class="sp-btn sp-btn-primary" :disabled="busy" @click="act('receive')"><span v-html="ICONS.check"></span>Mark as delivered</button>
      </template>
    </template>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="load">Try again</button>
    </div>

    <section class="sp-card st-steps" aria-label="Progress">
      <div v-if="!s" class="bc-skeleton sp-sk-line w70"></div>
      <template v-else-if="offTrack">
        <div class="st-off" :class="`t-${shipStatus(s.status).tone}`">
          <span v-html="ICONS[shipStatus(s.status).icon]"></span>
          <div><b>{{ shipStatus(s.status).label }}</b><small>This delivery didn't complete. Contact {{ supplier?.company_name ?? "the supplier" }} to arrange it again.</small></div>
        </div>
      </template>
      <ol v-else class="st-track">
        <li v-for="(key, i) in SHIP_STEPS" :key="key" :class="{ done: i < stepIndex, now: i === stepIndex }" :aria-current="i === stepIndex ? 'step' : undefined">
          <span class="st-dot"><span v-if="i < stepIndex" v-html="ICONS.check"></span><template v-else>{{ i + 1 }}</template></span>
          <span class="st-txt"><b>{{ SHIP_STATUS[key].label }}</b><small>{{ stepDate(key) }}</small></span>
        </li>
      </ol>
    </section>

    <div v-if="!s" class="st-grid">
      <div class="sp-card"><div class="bc-skeleton sp-sk-block"></div></div>
      <div class="sp-card"><div class="bc-skeleton sp-sk-block"></div></div>
    </div>
    <div v-else class="st-grid">
      <section class="sp-card" aria-labelledby="st-ship">
        <div class="sp-card-head"><div><h2 id="st-ship">Delivery</h2><p>The carrier and what it's bringing</p></div></div>
        <dl class="st-facts">
          <div><dt>Carrier</dt><dd>{{ s.carrier }}</dd></div>
          <div><dt>Tracking number</dt><dd class="mono">{{ s.tracking_number }}</dd></div>
          <div><dt>Shipped</dt><dd>{{ s.shipped_date ? date(s.shipped_date) : "Not yet" }}</dd></div>
          <div><dt>Delivered</dt><dd>{{ s.received_date ? date(s.received_date) : "Not yet" }}</dd></div>
          <div v-if="order?.expected_date"><dt>Expected</dt><dd>{{ date(order.expected_date) }}</dd></div>
        </dl>
        <div v-if="canEdit('deliveries') && !['delivered', 'failed', 'returned'].includes(s.status)" class="st-edit">
          <button v-if="!edit" type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="edit = { carrier: s.carrier, tracking_number: s.tracking_number, busy: false, error: '' }"><span v-html="ICONS.edit"></span>Change carrier or tracking</button>
          <form v-else class="st-edit-form" novalidate @submit.prevent="saveTracking">
            <div class="sp-grid-2">
              <label class="sp-field"><span class="sp-label">Carrier</span><input v-model.trim="edit.carrier" class="sp-input" maxlength="255" /></label>
              <label class="sp-field"><span class="sp-label">Tracking number</span><input v-model.trim="edit.tracking_number" class="sp-input" maxlength="255" /></label>
            </div>
            <p v-if="edit.error" class="sp-error">{{ edit.error }}</p>
            <div class="st-edit-act">
              <button type="button" class="sp-btn sp-btn-ghost sp-btn-sm" @click="edit = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary sp-btn-sm" :disabled="edit.busy">{{ edit.busy ? "Saving…" : "Save" }}</button>
            </div>
          </form>
          <button v-if="['in_transit', 'out_for_delivery'].includes(s.status)" type="button" class="st-link" @click="act('failed')">It didn't arrive</button>
        </div>
      </section>

      <section class="sp-card" aria-labelledby="st-order">
        <div class="sp-card-head">
          <div><h2 id="st-order">Purchase order</h2><p>{{ order?.order_number ?? "Order removed" }}</p></div>
          <router-link v-if="order && canView('sc_orders')" :to="{ name: 'OrderDetail', params: { id: order.id } }" class="sp-btn sp-btn-ghost sp-btn-sm">Open order</router-link>
        </div>
        <div v-if="supplier" class="st-sup">
          <span class="st-logo">{{ initials(supplier.company_name) }}</span>
          <div class="grow">
            <b>{{ supplier.company_name }}</b>
            <span>{{ supplier.contact_person }}</span>
            <a v-if="supplier.phone" :href="`tel:${supplier.phone}`">{{ supplier.phone }}</a>
            <a v-if="supplier.email" :href="`mailto:${supplier.email}`">{{ supplier.email }}</a>
            <span v-if="supplier.address" class="muted">{{ supplier.address }}</span>
          </div>
        </div>
        <ul v-if="(s.items ?? []).length" class="st-items">
          <li v-for="it in s.items" :key="it.id"><span>{{ it.warehouse_item?.product_name ?? "Item" }}</span><b>{{ it.quantity }}</b></li>
        </ul>
        <p class="sp-hint">When it arrives, receive and inspect it from the order so the right amounts go into stock.</p>
      </section>
    </div>
  </HrPage>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import HrPage from "../../../../../components/hr/HrPage.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { initials } from "../../../../../components/procurement/supplierUi";
import { SHIP_STATUS, SHIP_STEPS, shipStatus } from "../../../../../components/procurement/shipmentUi";
import { useAssignment } from "../../../../../composables/useAssignment";
import { shipmentService } from "../../../../../services/shipmentService";
import { toast } from "@/utils/snackbar";
import "../../../../../components/vendor/finance/finance.css";

const props = defineProps({ id: { type: [String, Number], required: true } });
const { canEdit, canView } = useAssignment();
const s = ref(null);
const loadError = ref("");
const busy = ref(false);
const edit = ref(null);

const order = computed(() => s.value?.purchase_order ?? null);
const supplier = computed(() => order.value?.supplier ?? null);
const offTrack = computed(() => ["failed", "returned"].includes(s.value?.status));
const stepIndex = computed(() => SHIP_STEPS.indexOf(s.value?.status));
const date = (d) => (d ? new Date(`${String(d).slice(0, 10)}T00:00:00`).toLocaleDateString("en-PH", { weekday: "short", month: "short", day: "numeric", year: "numeric" }) : "—");
function stepDate(key) {
  if (key === "in_transit" && s.value.shipped_date) return date(s.value.shipped_date);
  if (key === "delivered" && s.value.received_date) return date(s.value.received_date);
  return "";
}

async function load() {
  loadError.value = "";
  try {
    const res = await shipmentService.find(props.id);
    s.value = res.data;
  } catch (e) {
    loadError.value = apiMessage(e, "Couldn't load this shipment.");
  }
}

async function act(kind) {
  busy.value = true;
  try {
    if (kind === "ship") await shipmentService.markShipped(s.value.id);
    else if (kind === "receive") await shipmentService.markReceived(s.value.id);
    else await shipmentService.updateStatus(s.value.id, "failed");
    toast.success(kind === "ship" ? "Marked as shipped. The order is on the way." : kind === "receive" ? "Marked as delivered. Receive and inspect it from the order." : "Marked as not arrived.");
    await load();
  } catch (e) {
    toast.error(apiMessage(e, "Couldn't update the shipment."));
  } finally {
    busy.value = false;
  }
}

async function saveTracking() {
  const e = edit.value;
  e.busy = true;
  e.error = "";
  try {
    await shipmentService.updateTracking(s.value.id, { carrier: e.carrier || undefined, tracking_number: e.tracking_number || undefined });
    edit.value = null;
    toast.success("Tracking updated.");
    await load();
  } catch (err) {
    e.busy = false;
    e.error = apiMessage(err, "Couldn't save the tracking.");
  }
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
.st-steps {
  margin-bottom: 16px;
}
.st-track {
  list-style: none;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
  padding: 0;
}
.st-track li {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
}
.st-dot {
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
.st-dot :deep(svg) {
  width: 15px;
  height: 15px;
}
.st-track li.done .st-dot {
  border-color: var(--bc-accent);
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
}
.st-track li.now .st-dot {
  border-color: var(--bc-accent);
  color: var(--bc-accent-deep);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--bc-accent) 18%, transparent);
}
.st-txt {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.st-txt b {
  font-size: 13px;
  color: var(--v-muted);
}
.st-track li.done b,
.st-track li.now b {
  color: var(--v-text);
}
.st-txt small {
  font-size: 11.5px;
  color: var(--v-faint);
}
.st-off {
  display: flex;
  align-items: center;
  gap: 12px;
}
.st-off :deep(svg) {
  width: 22px;
  height: 22px;
  color: var(--v-red-text);
}
.st-off b {
  display: block;
  font-size: 14px;
  color: var(--v-text);
}
.st-off small {
  font-size: 12.5px;
  color: var(--v-text-2);
}
.st-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 16px;
  align-items: start;
}
.st-facts {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin: 0;
}
.st-facts div {
  padding: 10px 12px;
  border-radius: 12px;
  background: var(--v-subtle);
}
.st-facts dt {
  font-size: 11.5px;
  color: var(--v-muted);
}
.st-facts dd {
  margin: 2px 0 0;
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 13px !important;
}
.st-edit {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  margin-top: 14px;
}
.st-edit-form {
  display: grid;
  gap: 10px;
  width: 100%;
}
.st-edit-act {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
}
.st-link {
  padding: 0;
  border: 0;
  background: none;
  color: var(--v-red-text);
  font: inherit;
  font-size: 12.5px;
  text-decoration: underline;
  cursor: pointer;
}
.st-sup {
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
}
.st-sup b {
  font-size: 14px;
  color: var(--v-text);
}
.st-sup span,
.st-sup a {
  font-size: 12.5px;
  color: var(--v-text-2);
}
.st-sup .muted {
  color: var(--v-faint);
}
.st-logo {
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
.st-items {
  list-style: none;
  margin: 0 0 12px;
  padding: 0;
}
.st-items li {
  display: flex;
  justify-content: space-between;
  padding: 8px 0;
  border-top: 1px solid var(--v-border);
  font-size: 13px;
  color: var(--v-text-2);
}
@media (max-width: 1000px) {
  .st-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 640px) {
  .st-track {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
  .st-facts {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
