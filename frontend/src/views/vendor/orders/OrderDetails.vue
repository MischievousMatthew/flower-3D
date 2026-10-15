<template>
  <ShopLayout section="orders" :title="order ? order.order_number : 'Order details'" :subtitle="order ? `Placed ${formatDateTime(order.created_at)}` : ''">
    <template #actions>
      <router-link :to="{ name: 'VendorReservation' }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.arrowLeft"></span>All orders</router-link>
      <template v-if="order">
        <button v-if="order.can_cancel" type="button" class="sp-btn sp-btn-danger" @click="acting = { to: 'cancelled', label: 'Cancel order' }">Cancel order</button>
        <button v-for="n in order.next" :key="n.to" type="button" class="sp-btn sp-btn-primary" @click="acting = n">
          <span v-html="ICONS.check"></span>{{ n.label }}
        </button>
      </template>
    </template>

    <div v-if="error" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ error }}
      <button type="button" @click="load">Try again</button>
    </div>

    <!-- Loading -->
    <template v-if="!order && !error">
      <div class="sp-card"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton stepper-sk"></div></div>
      <div class="od-grid">
        <div class="sp-card"><div class="bc-skeleton sp-sk-block"></div><div class="bc-skeleton sp-sk-block"></div></div>
        <div class="sp-card"><div class="bc-skeleton sp-sk-block"></div></div>
      </div>
    </template>

    <template v-else-if="order">
      <div v-if="order.blocked_reason" class="sp-banner warn"><span v-html="ICONS.lock"></span>{{ order.blocked_reason }}</div>

      <!-- Status -->
      <section class="sp-card od-status" aria-label="Order status">
        <div class="od-status-head">
          <span class="sp-chip od-big-chip" :class="STATUS_TONE[order.status]">{{ order.status_label }}</span>
          <p>{{ STATUS_HINT[order.status] }}</p>
        </div>
        <ol v-if="!ENDED.includes(order.status)" class="stepper">
          <li v-for="(s, i) in order.stages" :key="s.key" :class="stepClass(i)">
            <span class="step-dot"><span v-if="i < currentStep || order.status === 'completed'" v-html="ICONS.check"></span><template v-else>{{ i + 1 }}</template></span>
            <span class="step-text">
              <strong>{{ s.label }}</strong>
              <small>{{ stepTime(s.key) }}</small>
            </span>
          </li>
        </ol>
      </section>

      <div class="od-grid">
        <!-- Left -->
        <div class="od-col">
          <section class="sp-card" aria-label="Items">
            <div class="sp-card-head">
              <div><h2>Items</h2><p>{{ order.items_count }} {{ order.items_count === 1 ? "item" : "items" }} to prepare</p></div>
              <button v-if="bouquet" type="button" class="sp-btn sp-btn-dark sp-btn-sm" @click="show3D(null)"><span v-html="ICONS.cube"></span>View 3D bouquet</button>
            </div>
            <ul class="items">
              <li v-for="item in order.items" :key="item.id" class="item">
                <img v-if="item.image" :src="item.image" :alt="item.name" class="item-img" loading="lazy" />
                <span v-else class="item-img blank" v-html="ICONS.flower"></span>
                <div class="item-main">
                  <div class="item-top">
                    <strong>{{ item.name }}</strong>
                    <span class="item-sub">{{ money(item.subtotal) }}</span>
                  </div>
                  <span class="item-qty">{{ item.quantity }} × {{ money(item.unit_price) }}</span>
                  <div v-if="hasDetails(item)" class="item-details">
                    <span v-if="item.is_custom_bouquet" class="sp-chip violet">Part of a custom 3D bouquet</span>
                    <span v-if="custom(item).paper_color" class="swatch"><i :style="{ background: custom(item).paper_color }"></i>Wrapper</span>
                    <span v-if="custom(item).ribbon_color" class="swatch"><i :style="{ background: custom(item).ribbon_color }"></i>Ribbon</span>
                    <span v-if="item.color" class="detail">Colour: {{ item.color }}</span>
                    <span v-if="item.size" class="detail">Size: {{ item.size }}</span>
                    <span v-for="(v, k) in extraCustom(item)" :key="k" class="detail">{{ k }}: {{ v }}</span>
                  </div>
                  <p v-if="item.notes" class="item-note">“{{ item.notes }}”</p>
                  <button v-if="!item.is_custom_bouquet && item.model_3d_url" type="button" class="link-btn" @click="show3D(item)"><span v-html="ICONS.cube"></span>View 3D model</button>
                </div>
              </li>
            </ul>
          </section>

          <section v-if="order.notes" class="sp-card note-card" aria-label="Customer note">
            <span class="note-icon" v-html="ICONS.note"></span>
            <div><h2>Note from the customer</h2><p>{{ order.notes }}</p></div>
          </section>

          <section v-if="order.return_case" class="sp-card" aria-label="Return or refund">
            <div class="sp-card-head"><div><h2>Return / refund</h2><p>Settle it with the customer here.</p></div></div>
            <ReturnCasePanel :case-id="order.return_case.id" role="vendor" api-base="/sc/order-requests" @changed="load" />
          </section>
        </div>

        <!-- Right -->
        <aside class="od-col">
          <section class="sp-card" aria-label="Delivery">
            <div class="sp-card-head"><div><h2>Delivery</h2><p>{{ order.fulfillment.handled_by }}</p></div></div>
            <div class="when" :class="{ late: isLate(order) }">
              <span class="when-icon" v-html="ICONS.calendar"></span>
              <div>
                <strong>{{ dayLabel(order.delivery_date, { long: true }) }}</strong>
                <span v-if="isLate(order)">Past its delivery date</span>
                <span v-else-if="order.delivery_date">{{ relativeDays(order.delivery_date) }}</span>
              </div>
            </div>
            <div v-if="order.prepare_by && !ENDED.includes(order.status) && order.status !== 'completed'" class="prep" :class="{ now: shouldPrepare(order) }">
              <span v-html="ICONS.clock"></span>
              <span>
                Start preparing <strong>{{ order.preparation_days ? dayLabel(order.prepare_by) : "on the day" }}</strong>
                <template v-if="order.preparation_days"> · {{ order.preparation_days }} {{ order.preparation_days === 1 ? "day" : "days" }} of prep</template>
              </span>
            </div>
            <dl class="facts">
              <div><dt><span v-html="ICONS.user"></span>Recipient</dt><dd>{{ order.customer }}</dd></div>
              <div v-if="order.phone"><dt><span v-html="ICONS.phone"></span>Phone</dt><dd><a :href="`tel:${order.phone}`">{{ order.phone }}</a></dd></div>
              <div v-if="order.address"><dt><span v-html="ICONS.pin"></span>Address</dt><dd>{{ order.address }}</dd></div>
              <div v-if="order.email"><dt><span v-html="ICONS.globe"></span>Email</dt><dd class="break">{{ order.email }}</dd></div>
            </dl>
          </section>

          <section class="sp-card" aria-label="Payment">
            <div class="sp-card-head">
              <div><h2>Payment</h2><p>{{ PAYMENT_LABEL[order.payment_method] ?? order.payment_method }}</p></div>
              <span class="sp-chip" :class="order.payment_status === 'paid' ? 'green' : order.payment_status === 'refunded' ? 'red' : 'amber'">
                {{ order.payment_status === "paid" ? "Paid" : order.payment_status === "refunded" ? "Refunded" : order.payment_method === "cod" ? "Collect on delivery" : "Unpaid" }}
              </span>
            </div>
            <dl class="totals">
              <div><dt>Items</dt><dd>{{ money(order.subtotal) }}</dd></div>
              <div><dt>Delivery fee</dt><dd>{{ money(order.delivery_fee) }}</dd></div>
              <div v-if="order.discount"><dt>Discount</dt><dd>−{{ money(order.discount) }}</dd></div>
              <div class="grand"><dt>Total</dt><dd>{{ money(order.total) }}</dd></div>
            </dl>
          </section>

          <section class="sp-card" aria-label="Activity">
            <div class="sp-card-head"><div><h2>Activity</h2></div></div>
            <ol class="activity">
              <li v-for="(t, i) in [...order.timeline].reverse()" :key="i">
                <span class="act-dot" :class="t.status"></span>
                <div><strong>{{ t.label }}</strong><span>{{ formatDateTime(t.at) }}</span></div>
              </li>
            </ol>
          </section>
        </aside>
      </div>
    </template>

    <OrderActionModal :order="order" :action="acting" @close="acting = null" @done="load" />
    <Order3DModal :model="viewer" @close="viewer = null" />
  </ShopLayout>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import OrderActionModal from "../../../components/vendor/orders/OrderActionModal.vue";
import Order3DModal from "../../../components/vendor/orders/Order3DModal.vue";
import ReturnCasePanel from "../../../components/returns/ReturnCasePanel.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../components/vendor/shop/useVendorCatalog";
import { linkedId } from "../../../utils/hiddenIds";
import {
  PAYMENT_LABEL, STATUS_HINT, STATUS_TONE, dayLabel, daysFromToday, formatDateTime, isLate, money, parseCustomizations, shouldPrepare,
} from "../../../components/vendor/orders/orderUi";
import "../../../components/vendor/shop/shop.css";

const router = useRouter();
const ENDED = ["cancelled", "returned", "refunded"];
// Keys stored with each customization that aren't worth showing
const HIDDEN_CUSTOM = ["type", "store_id", "vendor_id", "placement", "paper_color", "ribbon_color", "flowers", "bouquet_model_url", "model_3d_url", "scene_id"];

const order = ref(null);
const error = ref("");
const acting = ref(null);
const viewer = ref(null);
const orderId = linkedId("order");

async function load() {
  error.value = "";
  if (!orderId) {
    error.value = "Pick an order from the list to see its details.";
    return;
  }
  try {
    const { data } = await api.get(`vendor/fulfillment/orders/${orderId}`);
    order.value = data.data;
  } catch (e) {
    error.value = apiMessage(e, "Couldn't load this order.");
  }
}

const currentStep = computed(() => order.value?.stages.findIndex((s) => s.key === order.value.status) ?? -1);
const stepClass = (i) => ({
  done: i < currentStep.value || order.value.status === "completed",
  current: i === currentStep.value && order.value.status !== "completed",
});
function stepTime(key) {
  const hit = [...(order.value?.timeline ?? [])].reverse().find((t) => t.status === key || (key === "pending" && t.status === "placed"));
  return hit ? formatDateTime(hit.at) : "";
}
function relativeDays(key) {
  const n = daysFromToday(key);
  if (n === 0) return "Due today";
  if (n === 1) return "Due tomorrow";
  return n > 1 ? `In ${n} days` : "";
}

const custom = (item) => parseCustomizations(item.customizations) ?? {};
const extraCustom = (item) =>
  Object.fromEntries(
    Object.entries(custom(item))
      .filter(([k, v]) => !HIDDEN_CUSTOM.includes(k) && !/scale|_id$|url|path|position|rotation/i.test(k) && v !== null && v !== "" && typeof v !== "object")
      .map(([k, v]) => [k.replace(/_/g, " ").replace(/^\w/, (c) => c.toUpperCase()), v]),
  );
const hasDetails = (item) =>
  item.is_custom_bouquet || item.color || item.size || Object.keys(extraCustom(item)).length > 0;

// A custom 3D bouquet: either one item carrying the whole arrangement, or one item per placed flower
const bouquet = computed(() => {
  const items = order.value?.items ?? [];
  const whole = items.map(custom).find((c) => c?.type === "custom_flower_bouquet" && Array.isArray(c.flowers));
  if (whole) return whole;
  const placed = items.filter((i) => custom(i)?.type === "custom_flower_bouquet" && custom(i)?.placement);
  if (!placed.length) return null;
  const first = custom(placed[0]);
  return {
    type: "custom_flower_bouquet",
    bouquet_model_url: "/Boquet.glb",
    paper_color: first.paper_color,
    ribbon_color: first.ribbon_color,
    flowers: placed.map((i, index) => ({
      ...custom(i).placement,
      product_id: i.product_id,
      product_name: i.name,
      quantity: i.quantity,
      placement_order: index,
      scale: custom(i).placement.scale || { x: 1, y: 1, z: 1 },
    })),
  };
});
function show3D(item) {
  viewer.value = item
    ? { title: item.name, url: new URL(item.model_3d_url, window.location.origin).href, type: item.model_type || "glb", arrangement: null }
    : { title: "Custom bouquet", url: "/Boquet.glb", type: "glb", arrangement: bouquet.value };
}

onMounted(() => {
  if (!orderId) {
    router.replace({ name: "VendorReservation" });
    return;
  }
  load();
});
</script>

<style scoped>
.od-grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 360px;
  gap: 16px;
  align-items: start;
  margin-top: 16px;
}
.od-col {
  display: grid;
  gap: 16px;
  min-width: 0;
}
.stepper-sk {
  height: 56px;
  margin-top: 12px;
}

/* Status */
.od-status-head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
}
.od-big-chip {
  padding: 6px 14px;
  font-size: 13px;
}
.od-status-head p {
  margin: 0;
  font-size: 13px;
  color: var(--v-text-2);
}
.stepper {
  list-style: none;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  margin: 18px 0 0;
  padding: 0;
}
.stepper li {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  min-width: 0;
  text-align: center;
}
.stepper li:not(:last-child)::after {
  content: "";
  position: absolute;
  top: 15px;
  left: calc(50% + 20px);
  right: calc(-50% + 20px);
  height: 2px;
  border-radius: 2px;
  background: var(--v-subtle-2);
}
.stepper li.done:not(:last-child)::after {
  background: var(--bc-accent);
}
.step-dot {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 2px solid var(--v-border-strong);
  background: var(--v-surface);
  color: var(--v-faint);
  font-size: 12.5px;
  font-weight: 600;
}
.step-dot :deep(svg) {
  width: 15px;
  height: 15px;
}
.stepper li.done .step-dot {
  border-color: var(--bc-accent);
  background: var(--bc-accent);
  color: var(--bc-accent-ink);
}
.stepper li.current .step-dot {
  border-color: var(--bc-accent);
  color: var(--bc-accent-strong);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--bc-accent) 18%, transparent);
}
.step-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  max-width: 100%;
}
.step-text strong {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--v-text);
}
.stepper li:not(.done):not(.current) .step-text strong {
  color: var(--v-faint);
}
.step-text small {
  font-size: 11px;
  color: var(--v-faint);
  overflow-wrap: anywhere;
}

/* Items */
.items {
  list-style: none;
  margin: 0;
  padding: 0;
}
.item {
  display: flex;
  gap: 14px;
  padding: 14px 0;
  border-bottom: 1px solid var(--v-border);
}
.item:first-child {
  padding-top: 0;
}
.item:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}
.item-img {
  flex: none;
  width: 72px;
  height: 72px;
  border-radius: 14px;
  object-fit: cover;
  background: var(--bc-accent-soft);
}
.item-img.blank {
  display: grid;
  place-items: center;
  color: var(--bc-accent-border);
}
.item-img.blank :deep(svg) {
  width: 26px;
  height: 26px;
}
.item-main {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}
.item-top {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 10px;
}
.item-top strong {
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
  overflow-wrap: anywhere;
}
.item-sub {
  flex: none;
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
}
.item-qty {
  font-size: 12.5px;
  color: var(--v-muted);
}
.item-details {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 12px;
  margin-top: 6px;
}
.swatch,
.detail {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--v-text-2);
}
.swatch i {
  width: 14px;
  height: 14px;
  border-radius: 50%;
  border: 1px solid rgba(0, 0, 0, 0.12);
}
.item-note {
  margin: 6px 0 0;
  padding: 8px 10px;
  border-radius: 10px;
  background: var(--v-amber-soft);
  font-size: 12.5px;
  color: var(--v-amber-text);
  overflow-wrap: anywhere;
}
.link-btn {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: 6px;
  padding: 0;
  border: 0;
  background: none;
  font-family: "Poppins", sans-serif;
  font-size: 12.5px;
  font-weight: 600;
  color: var(--bc-accent-deep);
  cursor: pointer;
}
.link-btn :deep(svg) {
  width: 15px;
  height: 15px;
}
.note-card {
  display: flex;
  gap: 12px;
  background: var(--v-amber-soft);
  border-color: var(--v-amber-soft);
}
.note-card h2 {
  margin: 0 0 4px;
  font-size: 14px;
  font-weight: 600;
}
.note-card p {
  margin: 0;
  font-size: 13px;
  line-height: 1.55;
  color: var(--v-text-2);
  white-space: pre-line;
  overflow-wrap: anywhere;
}
.note-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 11px;
  background: var(--v-amber-soft);
  color: var(--v-amber-text);
}
.note-icon :deep(svg) {
  width: 18px;
  height: 18px;
}

/* Delivery */
.when {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px;
  border-radius: 14px;
  background: var(--bc-accent-soft);
}
.when.late {
  background: var(--v-red-soft);
}
.when-icon {
  flex: none;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--v-surface);
  color: var(--bc-accent-deep);
}
.when.late .when-icon {
  color: var(--v-red-text);
}
.when-icon :deep(svg) {
  width: 19px;
  height: 19px;
}
.when strong {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: var(--v-text);
}
.when span {
  font-size: 12px;
  color: var(--v-text-2);
}
.when.late span {
  color: var(--v-red-text);
}
.prep {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-top: 10px;
  font-size: 12.5px;
  color: var(--v-text-2);
}
.prep :deep(svg) {
  flex: none;
  width: 15px;
  height: 15px;
  margin-top: 2px;
  color: var(--v-faint);
}
.prep.now {
  color: var(--v-amber-text);
}
.facts {
  margin: 14px 0 0;
  display: grid;
  gap: 10px;
}
.facts > div {
  display: grid;
  grid-template-columns: 92px minmax(0, 1fr);
  gap: 8px;
}
.facts dt {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--v-muted);
}
.facts dt :deep(svg) {
  width: 14px;
  height: 14px;
}
.facts dd {
  margin: 0;
  font-size: 13px;
  color: var(--v-text);
  overflow-wrap: anywhere;
}
.facts dd.break {
  font-size: 12.5px;
  word-break: break-all;
}
.facts a {
  color: var(--bc-accent-deep);
  font-weight: 500;
  text-decoration: none;
}
.totals {
  margin: 0;
  display: grid;
  gap: 8px;
}
.totals > div {
  display: flex;
  justify-content: space-between;
  gap: 10px;
  font-size: 13px;
  color: var(--v-text-2);
}
.totals dd {
  margin: 0;
}
.totals .grand {
  padding-top: 10px;
  border-top: 1px dashed var(--v-border-strong);
  font-size: 15px;
  font-weight: 600;
  color: var(--v-text);
}
.activity {
  list-style: none;
  margin: 0;
  padding: 0;
}
.activity li {
  position: relative;
  display: flex;
  gap: 12px;
  padding-bottom: 14px;
}
.activity li:not(:last-child)::before {
  content: "";
  position: absolute;
  left: 5px;
  top: 14px;
  bottom: 0;
  width: 2px;
  background: var(--v-subtle-2);
}
.act-dot {
  flex: none;
  width: 12px;
  height: 12px;
  margin-top: 3px;
  border-radius: 50%;
  background: var(--v-border-strong);
}
.activity li:first-child .act-dot {
  background: var(--bc-accent);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--bc-accent) 18%, transparent);
}
.act-dot.cancelled {
  background: #a0aec0;
}
.activity strong {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: var(--v-text);
}
.activity span {
  font-size: 11.5px;
  color: var(--v-faint);
}
@media (max-width: 1100px) {
  .od-grid {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 640px) {
  .stepper {
    grid-template-columns: minmax(0, 1fr);
    gap: 10px;
  }
  .stepper li {
    flex-direction: row;
    text-align: left;
  }
  .stepper li:not(:last-child)::after {
    display: none;
  }
  .item-img {
    width: 56px;
    height: 56px;
  }
  .facts > div {
    grid-template-columns: minmax(0, 1fr);
    gap: 2px;
  }
}
</style>
