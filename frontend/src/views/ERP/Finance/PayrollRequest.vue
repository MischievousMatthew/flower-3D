<template>
  <HrPage title="Payroll requests" subtitle="Check the payslips HR prepared, approve them or send them back with a note, then mark them paid once the money is sent.">
    <div v-if="!perms.view" class="sp-card">
      <div class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.lock"></span>
        <h3>You can't see payroll requests</h3>
        <p>Ask the shop owner to give your role the Payroll requests permission.</p>
      </div>
    </div>
    <template v-else>
      <p v-if="!perms.approve && !perms.reject && !perms.pay" class="sp-banner info pr-banner"><span v-html="ICONS.alert"></span>View only — you can open payslips but not approve or pay them.</p>
      <PayrollDesk :api="financePayroll" mode="finance" :perms="perms" :employee-id="employeeId" />
    </template>
  </HrPage>
</template>

<script setup>
import { computed } from "vue";
import HrPage from "../../../components/hr/HrPage.vue";
import PayrollDesk from "../../../components/hr/payroll/PayrollDesk.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import { useAssignment } from "../../../composables/useAssignment";
import { useAuth } from "../../../composables/useAuth";
import { financePayroll } from "../../../services/payrollDesk";

const { can } = useAssignment();
const { user } = useAuth();
const employeeId = computed(() => Number(user.value?.id) || null);
// Finance doesn't create payrolls; that stays with HR
const perms = computed(() => ({
  view: can("payroll_requests", "view"),
  create: false,
  delete: false,
  approve: can("payroll_requests", "approve"),
  reject: can("payroll_requests", "reject"),
  pay: can("payroll_requests", "edit"),
}));
</script>

<style scoped>
.pr-banner {
  margin-bottom: 14px;
}
</style>
