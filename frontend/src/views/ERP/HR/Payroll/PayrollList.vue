<template>
  <HrPage title="Payroll" subtitle="Pay worked out from attendance, approved overtime, leave, allowances and deductions. Finance approves it before it's paid.">
    <div v-if="!perms.view" class="sp-card">
      <div class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.lock"></span>
        <h3>You can't see payroll</h3>
        <p>Salaries are only shown to people with the Payroll permission. Ask the shop owner if you need it.</p>
      </div>
    </div>
    <PayrollDesk v-else :api="hrPayroll" :perms="perms" :employee-id="employeeId" :start="start" />
  </HrPage>
</template>

<script setup>
import { computed } from "vue";
import HrPage from "../../../../components/hr/HrPage.vue";
import PayrollDesk from "../../../../components/hr/payroll/PayrollDesk.vue";
import { SHOP_ICONS as ICONS } from "../../../../components/vendor/shop/shopIcons";
import { useAssignment } from "../../../../composables/useAssignment";
import { useAuth } from "../../../../composables/useAuth";
import { hrPayroll } from "../../../../services/payrollDesk";

defineProps({
  // "new" opens the New payroll tab (the Create payroll menu link)
  start: { type: String, default: "runs" },
});

const { can } = useAssignment();
const { user } = useAuth();
const employeeId = computed(() => Number(user.value?.id) || null);
const perms = computed(() => ({
  view: can("payroll", "view"),
  create: can("payroll", "create"),
  delete: can("payroll", "delete"),
  approve: can("payroll_requests", "approve"),
  reject: can("payroll_requests", "reject"),
  pay: can("payroll_requests", "edit"),
}));
</script>
