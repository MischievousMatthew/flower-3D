<!-- A product's preparation time, one of two choices:
     - Ready the same day: it can be delivered today (until the shop closes),
       whatever the shop's same-day delivery switch says.
     - The shop's preparation time: the days set once in Shop settings.
     v-model is the product's ready_same_day (true / false). -->
<template>
  <fieldset class="pt">
    <legend class="pt-legend">Preparation time</legend>

    <div class="pt-opt" :class="{ on: modelValue }">
      <label class="pt-pick">
        <input type="radio" :name="name" :checked="modelValue" @change="emit('update:modelValue', true)" />
        <span>
          <b>Ready the same day</b>
          <small>Customers can get it today, until you close.</small>
        </span>
      </label>
      <InfoTip label="About “Ready the same day”" align="end">
        Customers can have this product delivered today, as long as they order before your closing time. This works even
        when same-day delivery is switched off in your Shop settings. An order only counts as same-day when every product
        in it is ready the same day.
      </InfoTip>
    </div>

    <div class="pt-opt" :class="{ on: !modelValue }">
      <label class="pt-pick">
        <input type="radio" :name="name" :checked="!modelValue" @change="emit('update:modelValue', false)" />
        <span>
          <b>My shop's preparation time</b>
          <small v-if="shop === undefined"><span class="bc-skeleton pt-sk"></span></small>
          <small v-else>{{ shopLine }}</small>
        </span>
      </label>
      <InfoTip label="About “My shop's preparation time”" align="end">
        This product follows the preparation time in your Shop settings{{ shop ? ` (now ${daysText})` : "" }}, so you don't
        set it product by product. Change it there and every product that uses it follows. Your shop's same-day delivery
        switch applies to these products too.
      </InfoTip>
    </div>

    <router-link :to="{ name: 'VendorShopSettings' }" target="_blank" rel="noopener" class="pt-link">Change it in Shop settings (opens a new tab)</router-link>
  </fieldset>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import api from "../../../plugins/axios";
import InfoTip from "./InfoTip.vue";

defineProps({ modelValue: { type: Boolean, default: false } });
const emit = defineEmits(["update:modelValue"]);

const name = `pt-${Math.random().toString(36).slice(2, 9)}`;
// undefined: loading; null: couldn't load (the choice still works); else the shop's settings
const shop = ref(undefined);

const daysText = computed(() => {
  const d = Number(shop.value?.lead_time_days) || 0;
  return d === 0 ? "no extra days" : `${d} ${d === 1 ? "day" : "days"}`;
});
const shopLine = computed(() => {
  if (!shop.value) return "The preparation time from your Shop settings.";
  const sameDay = shop.value.same_day_delivery ? " · same-day delivery is on" : "";
  return `Now: ${daysText.value}${sameDay}`;
});

onMounted(async () => {
  try {
    const { data } = await api.get("vendor/account/shop-settings");
    shop.value = data?.data ?? null;
  } catch {
    shop.value = null;
  }
});
</script>

<style scoped>
.pt {
  display: grid;
  gap: 8px;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}
.pt-legend {
  margin-bottom: 6px;
  padding: 0;
  font-size: 13px;
  font-weight: 500;
  color: var(--v-text-2);
}
.pt-opt {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 10px 10px 12px;
  border: 1px solid var(--v-border);
  border-radius: 12px;
  background: var(--v-surface);
  transition: border-color 0.15s, background 0.15s;
}
.pt-opt.on {
  border-color: var(--bc-accent);
  background: var(--v-subtle);
}
.pt-pick {
  display: flex;
  flex: 1;
  align-items: flex-start;
  gap: 10px;
  min-width: 0;
  min-height: 44px;
  cursor: pointer;
}
.pt-pick input {
  width: 18px;
  height: 18px;
  margin: 3px 0 0;
  flex: none;
  accent-color: var(--bc-accent);
}
.pt-pick > span {
  display: grid;
  gap: 2px;
  min-width: 0;
}
.pt-pick b {
  font-size: 13.5px;
  font-weight: 600;
  color: var(--v-text);
}
.pt-pick small {
  font-size: 12.5px;
  color: var(--v-muted);
}
.pt-sk {
  display: inline-block;
  width: 120px;
  height: 12px;
  border-radius: 6px;
}
.pt-link {
  justify-self: start;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--bc-accent-deep, var(--bc-accent));
  text-decoration: none;
}
.pt-link:hover {
  text-decoration: underline;
}
.pt-link:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
  border-radius: 4px;
}
@media (prefers-reduced-motion: reduce) {
  .pt-opt {
    transition: none;
  }
}
</style>
