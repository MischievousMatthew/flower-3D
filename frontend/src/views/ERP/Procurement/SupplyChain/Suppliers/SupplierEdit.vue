<template>
  <HrPage :title="supplier ? `Edit ${supplier.company_name}` : 'Edit supplier'" subtitle="Details, delivery schedule, terms and contacts. The catalog and ratings are on the supplier's profile.">
    <template #actions>
      <router-link :to="{ name: 'SupplierProfile', params: { id } }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.chevronLeft"></span>Back to profile</router-link>
    </template>

    <div v-if="loadError" class="sp-banner error">
      <span v-html="ICONS.alert"></span>{{ loadError }}
      <button type="button" @click="load">Try again</button>
    </div>

    <div v-if="!supplier && !loadError" class="se-sk">
      <div class="sp-card"><div class="bc-skeleton sp-sk-line w30"></div><div class="bc-skeleton sp-sk-block"></div></div>
      <div class="sp-card"><div class="bc-skeleton sp-sk-line w50"></div><div class="bc-skeleton sp-sk-block"></div></div>
    </div>
    <SupplierForm v-else-if="supplier" :initial="supplier" :saving="saving" :server-errors="errors" :form-error="formError" show-status submit-label="Save changes" :cancel-to="{ name: 'SupplierProfile', params: { id } }" @submit="save">
      <template #side>
        <dl class="se-facts">
          <div><dt>Added</dt><dd>{{ date(supplier.created_at) }}</dd></div>
          <div><dt>Last changed</dt><dd>{{ date(supplier.updated_at) }}</dd></div>
          <div><dt>Purchase orders</dt><dd>{{ supplier.purchase_orders?.length ?? 0 }}</dd></div>
        </dl>
      </template>
    </SupplierForm>
  </HrPage>
</template>

<script setup>
import { onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import HrPage from "../../../../../components/hr/HrPage.vue";
import SupplierForm from "../../../../../components/procurement/SupplierForm.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { supplierService } from "../../../../../services/supplierService";
import { toast } from "@/utils/snackbar";

const props = defineProps({ id: { type: [String, Number], required: true } });
const router = useRouter();
const supplier = ref(null);
const loadError = ref("");
const saving = ref(false);
const errors = ref({});
const formError = ref("");

const date = (d) => (d ? new Date(d).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—");

async function load() {
  loadError.value = "";
  try {
    const res = await supplierService.find(props.id);
    supplier.value = res.data;
  } catch (e) {
    loadError.value = apiMessage(e, "Couldn't load this supplier.");
  }
}

async function save(fd) {
  saving.value = true;
  errors.value = {};
  formError.value = "";
  try {
    await supplierService.update(props.id, fd);
    toast.success("Changes saved.");
    router.push({ name: "SupplierProfile", params: { id: props.id } });
  } catch (e) {
    errors.value = e?.response?.data?.errors ?? {};
    formError.value = apiMessage(e, "Couldn't save the changes.");
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.se-sk {
  display: grid;
  gap: 16px;
}
.se-sk .sp-card {
  display: grid;
  gap: 12px;
}
.se-facts {
  display: grid;
  gap: 8px;
  margin: 4px 0;
  padding: 12px;
  border-radius: 12px;
  background: var(--v-subtle);
}
.se-facts div {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  font-size: 12.5px;
}
.se-facts dt {
  color: var(--v-muted);
}
.se-facts dd {
  margin: 0;
  font-weight: 600;
  color: var(--v-text);
}
</style>
