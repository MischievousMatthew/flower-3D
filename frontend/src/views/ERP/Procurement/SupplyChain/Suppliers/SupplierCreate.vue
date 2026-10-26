<template>
  <HrPage title="Add a supplier" subtitle="A farm, wholesaler, packaging or supply vendor the shop buys from. You can add their catalog and prices on their profile next.">
    <template #actions>
      <router-link :to="{ name: 'SupplierList' }" class="sp-btn sp-btn-ghost"><span v-html="ICONS.chevronLeft"></span>All suppliers</router-link>
    </template>
    <SupplierForm :saving="saving" :server-errors="errors" :form-error="formError" submit-label="Add supplier" @submit="save" />
  </HrPage>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import HrPage from "../../../../../components/hr/HrPage.vue";
import SupplierForm from "../../../../../components/procurement/SupplierForm.vue";
import { SHOP_ICONS as ICONS } from "../../../../../components/vendor/shop/shopIcons";
import { apiMessage } from "../../../../../components/vendor/shop/useVendorCatalog";
import { supplierService } from "../../../../../services/supplierService";
import { toast } from "@/utils/snackbar";

const router = useRouter();
const saving = ref(false);
const errors = ref({});
const formError = ref("");

async function save(fd) {
  saving.value = true;
  errors.value = {};
  formError.value = "";
  try {
    const res = await supplierService.create(fd);
    const supplier = res.data;
    toast.success(`${supplier?.company_name ?? "The supplier"} was added.`, supplier?.id ? { action: { label: "Add catalog", to: { name: "SupplierProfile", params: { id: supplier.id }, query: { tab: "catalog" } } } } : {});
    router.push(supplier?.id ? { name: "SupplierProfile", params: { id: supplier.id } } : { name: "SupplierList" });
  } catch (e) {
    errors.value = e?.response?.data?.errors ?? {};
    formError.value = apiMessage(e, "Couldn't add the supplier.");
  } finally {
    saving.value = false;
  }
}
</script>
