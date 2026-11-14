<!-- "Supplier & notes" helper on Add product: pick a supplier saved in
     Procurement → Supply chain → Suppliers, and the product's supplier name and
     contact fill in. Shops without the ERP (or staff who can't see suppliers)
     keep typing them by hand; the tip says where to save suppliers once.
     Emits "pick" with the chosen supplier ({ company_name, phone, email, … }). -->
<template>
  <div class="spk">
    <div class="spk-head">
      <span class="sp-label">Saved suppliers</span>
      <InfoTip label="About saved suppliers" align="start">
        <template v-if="allowed">
          Pick a supplier you saved in Procurement → Supply chain → Suppliers and its name and contact fill in for you.
          Add a supplier there once instead of typing it on every product. You can still change the fields after picking.
        </template>
        <template v-else>
          Shops with the ERP (Procurement) can save their suppliers once in Procurement → Supply chain → Suppliers and pick
          them here instead of typing them on every product. For now, type the supplier below.
        </template>
      </InfoTip>
    </div>

    <template v-if="allowed">
      <span v-if="suppliers === undefined" class="bc-skeleton spk-sk" aria-label="Loading your suppliers"></span>

      <p v-else-if="suppliers === null" class="sp-hint">
        Couldn't load your saved suppliers, so type the supplier below.
        <button type="button" class="spk-link" @click="load">Try again</button>
      </p>

      <p v-else-if="!suppliers.length" class="sp-hint">
        No saved suppliers yet.
        <router-link :to="{ name: 'SupplierCreate' }" target="_blank" rel="noopener" class="spk-link">Add one in Supply chain (opens a new tab)</router-link>,
        then <button type="button" class="spk-link" @click="load">reload the list</button>.
      </p>

      <div v-else class="spk-row">
        <select class="sp-select" :value="selectedId" aria-label="Choose a saved supplier" @change="onPick($event.target.value)">
          <option value="">Choose a saved supplier…</option>
          <option v-for="s in suppliers" :key="s.id" :value="String(s.id)">{{ s.company_name }}{{ s.contact_person ? ` · ${s.contact_person}` : "" }}</option>
        </select>
        <router-link :to="{ name: 'SupplierCreate' }" target="_blank" rel="noopener" class="spk-link spk-add">New supplier</router-link>
        <button type="button" class="spk-link" @click="load">Reload</button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { supplierService } from "../../../services/supplierService";
import { useSubscriptionAccess } from "../../../composables/useSubscriptionAccess";
import { SUBSCRIPTION_MODULES } from "../../../constants/subscriptionModules";
import InfoTip from "./InfoTip.vue";

// The product's current supplier name, so a matching saved supplier shows as picked
const props = defineProps({ name: { type: String, default: "" } });
const emit = defineEmits(["pick"]);

const { subscriptionAccess, subscriptionAllowsModule } = useSubscriptionAccess();
const allowed = computed(() => subscriptionAllowsModule(subscriptionAccess.value, SUBSCRIPTION_MODULES.SUPPLIERS));

// undefined: loading; null: couldn't load (typing still works); else the active suppliers
const suppliers = ref(undefined);

const selectedId = computed(() => {
  const n = (props.name || "").trim().toLowerCase();
  const s = n && (suppliers.value || []).find((x) => (x.company_name || "").trim().toLowerCase() === n);
  return s ? String(s.id) : "";
});

function onPick(id) {
  const s = (suppliers.value || []).find((x) => String(x.id) === id);
  if (s) emit("pick", s);
}

async function load() {
  suppliers.value = undefined;
  try {
    const res = await supplierService.list({ status: "active", per_page: 100 });
    suppliers.value = res.data?.data ?? [];
  } catch {
    suppliers.value = null;
  }
}

onMounted(() => {
  if (allowed.value) load();
});
</script>

<style scoped>
.spk {
  display: grid;
  gap: 8px;
  min-width: 0;
}
.spk-head {
  display: flex;
  align-items: center;
  gap: 6px;
}
.spk-head .sp-label {
  margin: 0;
}
.spk-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 14px;
}
.spk-row .sp-select {
  flex: 1 1 260px;
  min-width: 0;
}
.spk-sk {
  display: block;
  height: 42px;
  border-radius: 10px;
}
.spk-link {
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--bc-accent-deep, var(--bc-accent));
  text-decoration: none;
  cursor: pointer;
}
.spk-row .spk-link {
  min-height: 44px;
  display: inline-flex;
  align-items: center;
}
.spk-link:hover {
  text-decoration: underline;
}
.spk-link:focus-visible {
  outline: 2px solid var(--bc-accent);
  outline-offset: 2px;
  border-radius: 4px;
}
</style>
