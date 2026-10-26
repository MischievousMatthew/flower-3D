<template>
  <HrPage title="Shipments" subtitle="Track supplier deliveries for your purchase orders, from the carrier picking them up to the delivery at your door.">
    <template #actions>
      <button v-if="canEdit('deliveries')" type="button" class="sp-btn sp-btn-primary" @click="openCreate"><span v-html="ICONS.plus"></span>Track a delivery</button>
    </template>

    <div class="sp-card sl-bar">
      <div class="sp-seg" role="group" aria-label="Status">
        <button v-for="t in TABS" :key="t.value" type="button" :class="{ on: status === t.value }" :aria-pressed="status === t.value" @click="setStatus(t.value)">{{ t.label }}</button>
      </div>
    </div>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="load">Try again</button>
    </div>

    <section class="sp-card sl-card" aria-label="Shipments">
      <ul v-if="rows === null" class="sl-rows"><li v-for="n in 5" :key="n" class="sl-row"><div class="bc-skeleton sp-sk-line w50"></div></li></ul>
      <div v-else-if="!rows.length" class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.truck"></span>
        <h3>{{ status ? `No shipments ${shipStatus(status).label.toLowerCase()}` : "No shipments yet" }}</h3>
        <p>Add the carrier and tracking number when a supplier sends an order, then follow it here until it arrives.</p>
        <button v-if="!status && canEdit('deliveries')" type="button" class="sp-btn sp-btn-primary" @click="openCreate"><span v-html="ICONS.plus"></span>Track a delivery</button>
      </div>
      <template v-else>
        <ul class="sl-rows">
          <li v-for="s in rows" :key="s.id">
            <router-link :to="{ name: 'ShipmentTracking', params: { id: s.id } }" class="sl-row">
              <span class="sl-ic" :class="`tone-${shipStatus(s.status).tone}`" v-html="ICONS[shipStatus(s.status).icon]"></span>
              <span class="grow">
                <b>{{ s.tracking_number }}</b>
                <small>{{ s.carrier }} · {{ s.purchase_order?.order_number ?? "Order removed" }}</small>
              </span>
              <span class="sl-dates">
                <small>{{ s.shipped_date ? `Shipped ${date(s.shipped_date)}` : "Not shipped yet" }}</small>
                <small v-if="s.received_date">Arrived {{ date(s.received_date) }}</small>
              </span>
              <span class="sp-chip" :class="shipStatus(s.status).tone">{{ shipStatus(s.status).label }}</span>
            </router-link>
          </li>
        </ul>
        <ShowMore tone="vendor" :has-more="pager.hasMore.value" :loading="pager.loadingMore.value" :shown="rows.length" :total="pager.total.value" @more="pager.showMore()" />
      </template>
    </section>

    <!-- Track a delivery -->
    <Teleport to="body">
      <Transition name="sp-fade"><div v-if="form" class="sp-backdrop" @click="form = null"></div></Transition>
      <Transition name="sp-pop">
        <div v-if="form" class="sp-modal-wrap">
          <form class="sp-modal sl-form" role="dialog" aria-modal="true" aria-labelledby="sl-new" novalidate @submit.prevent="create">
            <div class="sp-modal-head">
              <div><h3 id="sl-new">Track a supplier delivery</h3><p>For an order that's with the supplier. You can mark it shipped and delivered as it moves.</p></div>
              <button type="button" class="sp-icon-btn" aria-label="Close" @click="form = null"><span v-html="ICONS.close"></span></button>
            </div>
            <label class="sp-field">
              <span class="sp-label">Purchase order</span>
              <select v-model="form.purchase_order_id" class="sp-select" :class="{ invalid: form.errors.purchase_order_id }">
                <option value="">{{ orders === null ? "Loading…" : orders.length ? "Choose an order" : "No orders are waiting on a supplier" }}</option>
                <option v-for="o in orders ?? []" :key="o.id" :value="o.id">{{ o.order_number }} · {{ o.supplier?.company_name }}</option>
              </select>
              <small v-if="form.errors.purchase_order_id" class="sp-error">{{ form.errors.purchase_order_id }}</small>
            </label>
            <div class="sp-grid-2">
              <label class="sp-field">
                <span class="sp-label">Carrier</span>
                <input v-model.trim="form.carrier" class="sp-input" :class="{ invalid: form.errors.carrier }" maxlength="255" list="sl-carriers" placeholder="e.g. LBC, J&T, Lalamove" />
                <datalist id="sl-carriers"><option v-for="c in CARRIERS" :key="c" :value="c" /></datalist>
                <small v-if="form.errors.carrier" class="sp-error">{{ form.errors.carrier }}</small>
              </label>
              <label class="sp-field">
                <span class="sp-label">Tracking number <small>(optional)</small></span>
                <input v-model.trim="form.tracking_number" class="sp-input" maxlength="255" placeholder="One is made if empty" />
              </label>
            </div>
            <p v-if="form.error" class="sp-error">{{ form.error }}</p>
            <div class="sp-modal-foot">
              <button type="button" class="sp-btn sp-btn-ghost" @click="form = null">Cancel</button>
              <button type="submit" class="sp-btn sp-btn-primary" :disabled="form.busy">{{ form.busy ? "Saving…" : "Start tracking" }}</button>
            </div>
          </form>
        </div>
      </Transition>
    </Teleport>
  </HrPage>
</template>

<script setup>
import { ref, watch, onMounted } from "vue";
import { useRouter } from "vue-router";
import HrPage from "../../../../../components/hr/HrPage.vue";
import ShowMore from "../../../../../components/common/ShowMore.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { SHIP_STATUS, shipStatus } from "../../../../../components/procurement/shipmentUi";
import { useShowMorePaged } from "../../../../../composables/useShowMore";
import { useAssignment } from "../../../../../composables/useAssignment";
import { shipmentService } from "../../../../../services/shipmentService";
import { orderService } from "../../../../../services/orderService";
import { toast } from "@/utils/snackbar";

const TABS = [{ value: "", label: "All" }, ...Object.entries(SHIP_STATUS).map(([value, s]) => ({ value, label: s.label }))];
const CARRIERS = ["LBC", "J&T Express", "Lalamove", "Grab Express", "2GO", "Supplier's own truck"];

const router = useRouter();
const { canEdit } = useAssignment();
const status = ref("");
const rows = ref(null);
const loadError = ref("");
const form = ref(null);
const orders = ref(null);

const date = (d) => (d ? new Date(`${String(d).slice(0, 10)}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—");

async function fetchPage(page) {
  const res = await shipmentService.list({ status: status.value || undefined, per_page: 20, page });
  const body = res.data ?? {};
  return { items: body.data ?? [], lastPage: body.last_page ?? 1, total: body.total };
}
const pager = useShowMorePaged(fetchPage);
watch(() => pager.visible.value, (v) => {
  if (rows.value !== null) rows.value = v;
});
async function load() {
  loadError.value = "";
  try {
    await pager.load();
    if (pager.failure.value) throw pager.failure.value;
    rows.value = pager.visible.value;
  } catch (e) {
    rows.value = rows.value ?? [];
    loadError.value = apiMessage(e, "Couldn't load the shipments.");
  }
}
function setStatus(s) {
  status.value = s;
  load();
}

async function openCreate() {
  form.value = { purchase_order_id: "", carrier: "", tracking_number: "", errors: {}, error: "", busy: false };
  if (orders.value === null) {
    try {
      const res = await orderService.list({ stage: "ordered", per_page: 100 });
      // Only orders the supplier hasn't shipped yet, without a shipment
      orders.value = (res.data?.data ?? []).filter((o) => ["pending", "processing"].includes(o.status));
    } catch {
      orders.value = [];
    }
  }
}
async function create() {
  const f = form.value;
  f.errors = {};
  f.error = "";
  if (!f.purchase_order_id) f.errors.purchase_order_id = "Choose the order.";
  if (!f.carrier) f.errors.carrier = "Enter who's delivering it.";
  if (Object.keys(f.errors).length) return;
  f.busy = true;
  try {
    const res = await shipmentService.create({ purchase_order_id: f.purchase_order_id, carrier: f.carrier, tracking_number: f.tracking_number || null });
    form.value = null;
    orders.value = null;
    toast.success(`Tracking ${res.data.tracking_number}.`);
    router.push({ name: "ShipmentTracking", params: { id: res.data.id } });
  } catch (e) {
    f.busy = false;
    for (const [k, v] of Object.entries(e?.response?.data?.errors ?? {})) f.errors[k] = v[0];
    f.error = Object.keys(f.errors).length ? "" : apiMessage(e, "Couldn't start tracking it.");
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
.sl-bar {
  padding: 12px;
  margin-bottom: 16px;
  overflow-x: auto;
}
.sl-bar .sp-seg {
  width: max-content;
}
.sl-card {
  padding: 8px;
}
.sl-rows {
  list-style: none;
  margin: 0;
  padding: 0;
}
.sl-rows > li + li {
  border-top: 1px solid var(--v-border);
}
.sl-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 10px;
  border-radius: 12px;
  color: inherit;
  text-decoration: none;
}
a.sl-row:hover {
  background: var(--v-subtle);
}
a.sl-row:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: -2px;
}
.sl-ic {
  flex: none;
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 11px;
}
.sl-ic :deep(svg) {
  width: 17px;
  height: 17px;
}
.sl-row b {
  font-size: 13.5px;
  color: var(--v-text);
}
.sl-row small {
  font-size: 12px;
  color: var(--v-muted);
}
.sl-dates {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 2px;
}
.sl-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
@media (max-width: 640px) {
  .sl-row {
    flex-wrap: wrap;
  }
  .sl-dates {
    align-items: flex-start;
    order: 5;
    width: 100%;
    padding-left: 52px;
  }
}
</style>
