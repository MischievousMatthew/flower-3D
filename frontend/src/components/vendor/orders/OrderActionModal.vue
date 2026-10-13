<template>
  <Teleport to="body">
    <Transition name="sp-fade"><div v-if="action" class="sp-backdrop" @click="close"></div></Transition>
    <Transition name="sp-pop">
      <div v-if="action" class="sp-modal-wrap">
        <div class="sp-modal" role="alertdialog" aria-modal="true" aria-labelledby="oa-title">
          <div class="sp-modal-head">
            <div>
              <h3 id="oa-title">{{ cancelling ? "Cancel this order?" : `${action.label}?` }}</h3>
              <p>{{ order.order_number }} · {{ order.customer }}</p>
            </div>
          </div>
          <div class="oa-flow">
            <span class="sp-chip" :class="STATUS_TONE[order.status]">{{ order.status_label }}</span>
            <span class="oa-arrow" v-html="ICONS.chevronRight"></span>
            <span class="sp-chip" :class="STATUS_TONE[action.to]">{{ targetLabel }}</span>
          </div>
          <p class="oa-text">{{ explanation }}</p>
          <p v-if="error" class="sp-error">{{ error }}</p>
          <div class="sp-modal-foot">
            <button type="button" class="sp-btn sp-btn-ghost" :disabled="busy" @click="close">Not now</button>
            <button type="button" class="sp-btn" :class="cancelling ? 'sp-btn-danger' : 'sp-btn-primary'" :disabled="busy" @click="confirm">
              {{ busy ? "Saving…" : cancelling ? "Cancel order" : action.label }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import { SHOP_ICONS as ICONS } from "../shop/shopIcons";
import { apiMessage } from "../shop/useVendorCatalog";
import { STATUS_TONE } from "./orderUi";

// action: { to, label } from the order's `next` list, or { to: "cancelled" }
const props = defineProps({ order: { type: Object, default: null }, action: { type: Object, default: null } });
const emit = defineEmits(["close", "done"]);

const LABEL = { to_processed: "Preparing", to_ship: "Ready", to_received: "Out for delivery", completed: "Completed", cancelled: "Cancelled" };
const TEXT = {
  to_processed: "The customer sees that you've confirmed their order and started preparing it.",
  to_ship: "The arrangement is done and packed, waiting for the rider.",
  to_received: "The order has left your shop. The customer can track it as out for delivery.",
  completed: "The customer has received their order.",
  cancelled: "The customer is told the order was cancelled and any reserved stock goes back to your inventory. This can't be undone.",
};

const busy = ref(false);
const error = ref("");
const cancelling = computed(() => props.action?.to === "cancelled");
const targetLabel = computed(() => LABEL[props.action?.to] ?? props.action?.to);
const explanation = computed(() => TEXT[props.action?.to] ?? "");

watch(() => props.action, () => (error.value = ""));

async function confirm() {
  if (busy.value) return;
  busy.value = true;
  error.value = "";
  try {
    const { data } = await api.put(`vendor/orders/${props.order.id}/status`, { status: props.action.to });
    toast.success(cancelling.value ? `${props.order.order_number} cancelled.` : `${props.order.order_number}: ${targetLabel.value}.`);
    emit("done", data.data);
    emit("close");
  } catch (e) {
    error.value = apiMessage(e, "Couldn't update the order.");
  } finally {
    busy.value = false;
  }
}

function close() {
  if (!busy.value) emit("close");
}
</script>

<style scoped>
.oa-flow {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
}
.oa-arrow {
  display: inline-flex;
  color: #a0aec0;
}
.oa-arrow :deep(svg) {
  width: 16px;
  height: 16px;
}
.oa-text {
  margin: 0;
  font-size: 13px;
  line-height: 1.55;
  color: #4a5568;
}
</style>
