<template>
  <Teleport to="body">
    <Transition name="sp-fade"><div v-if="product" class="sp-backdrop" @click="close"></div></Transition>
    <Transition name="sp-pop">
      <div v-if="product" class="sp-modal-wrap">
        <form class="sp-modal" role="dialog" aria-modal="true" aria-labelledby="sale-title" @submit.prevent="save(false)">
          <div class="sp-modal-head">
            <div>
              <h3 id="sale-title">Sale price</h3>
              <p>{{ product.name }} · regular price {{ money(product.price) }}</p>
            </div>
            <button type="button" class="sp-icon-btn" aria-label="Close" @click="close"><span v-html="ICONS.close"></span></button>
          </div>

          <div class="sale-modes">
            <button type="button" class="sp-pick" :class="{ on: mode === 'price' }" @click="mode = 'price'">Set a price</button>
            <button type="button" class="sp-pick" :class="{ on: mode === 'percent' }" @click="mode = 'percent'">Take a % off</button>
          </div>

          <div class="sp-grid-2">
            <label v-if="mode === 'price'" class="sp-field sp-span-all">
              <span>Sale price (₱)</span>
              <input v-model.number="price" type="number" min="0.01" step="0.01" :max="product.price - 0.01" class="sp-input" required />
            </label>
            <label v-else class="sp-field sp-span-all">
              <span>Percent off</span>
              <input v-model.number="percent" type="number" min="1" max="90" step="1" class="sp-input" required />
            </label>
            <label class="sp-field">
              <span>Starts</span>
              <input v-model="startsAt" type="datetime-local" class="sp-input" />
              <small>Leave empty to start now</small>
            </label>
            <label class="sp-field">
              <span>Ends</span>
              <input v-model="endsAt" type="datetime-local" class="sp-input" :min="startsAt || undefined" />
              <small>Leave empty to keep it on</small>
            </label>
          </div>

          <div v-if="salePrice" class="sale-preview">
            <span class="old">{{ money(product.price) }}</span>
            <strong>{{ money(salePrice) }}</strong>
            <span class="sp-chip green">−{{ Math.round((1 - salePrice / product.price) * 100) }}%</span>
          </div>
          <p v-if="product.sale_source === 'promotion'" class="sp-hint">
            “{{ product.promotion_name }}” is running on this product. Customers always get the lower of the two prices.
          </p>
          <p v-if="error" class="sp-error">{{ error }}</p>

          <div class="sp-modal-foot">
            <button v-if="product.own_discount !== null" type="button" class="sp-btn sp-btn-danger" :disabled="saving" @click="save(true)">
              Remove sale
            </button>
            <span class="grow"></span>
            <button type="button" class="sp-btn sp-btn-ghost" @click="close">Cancel</button>
            <button type="submit" class="sp-btn sp-btn-primary" :disabled="saving || !salePrice">{{ saving ? "Saving…" : "Save" }}</button>
          </div>
        </form>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { toast } from "vue3-toastify";
import api from "../../../plugins/axios";
import { SHOP_ICONS as ICONS } from "./shopIcons";
import { apiMessage, money } from "./useVendorCatalog";

const props = defineProps({ product: { type: Object, default: null } });
const emit = defineEmits(["close", "saved"]);

const mode = ref("price");
const price = ref(null);
const percent = ref(10);
const startsAt = ref("");
const endsAt = ref("");
const saving = ref(false);
const error = ref("");

// <input type="datetime-local"> works in local time without a zone
const toLocalInput = (iso) => {
  if (!iso) return "";
  const d = new Date(iso);
  const pad = (n) => String(n).padStart(2, "0");
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};
const toIso = (local) => (local ? new Date(local).toISOString() : null);

watch(
  () => props.product,
  (p) => {
    error.value = "";
    if (!p) return;
    mode.value = "price";
    price.value = p.own_discount ?? Math.round(p.price * 0.9 * 100) / 100;
    percent.value = p.own_discount ? Math.round((1 - p.own_discount / p.price) * 100) : 10;
    startsAt.value = toLocalInput(p.discount_starts_at);
    endsAt.value = toLocalInput(p.discount_ends_at);
  },
  { immediate: true },
);

const salePrice = computed(() => {
  if (!props.product) return null;
  const value = mode.value === "price"
    ? Number(price.value)
    : Math.round(props.product.price * (1 - Number(percent.value) / 100) * 100) / 100;
  return value > 0 && value < props.product.price ? value : null;
});

async function save(remove) {
  if (saving.value) return;
  error.value = "";
  if (!remove && endsAt.value && new Date(endsAt.value) <= new Date(startsAt.value || Date.now())) {
    error.value = "The sale must end after it starts.";
    return;
  }
  saving.value = true;
  try {
    const { data } = await api.patch(`vendor/catalog/products/${props.product.id}/discount`,
      remove
        ? { discount_price: null }
        : { discount_price: salePrice.value, starts_at: toIso(startsAt.value), ends_at: toIso(endsAt.value) });
    toast.success(data.message);
    emit("saved", data.data);
    emit("close");
  } catch (e) {
    error.value = apiMessage(e, "Couldn't save the sale price.");
  } finally {
    saving.value = false;
  }
}

function close() {
  if (!saving.value) emit("close");
}
</script>

<style scoped>
.sale-modes {
  display: flex;
  gap: 8px;
  margin-bottom: 14px;
}
.sale-preview {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-top: 16px;
  padding: 12px 14px;
  border-radius: 13px;
  background: var(--bc-accent-soft);
}
.sale-preview .old {
  font-size: 13px;
  color: var(--v-faint);
  text-decoration: line-through;
}
.sale-preview strong {
  font-size: 18px;
  font-weight: 600;
  color: var(--bc-accent-strong);
}
.grow {
  flex: 1;
}
</style>
