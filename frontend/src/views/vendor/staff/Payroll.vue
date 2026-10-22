<template>
  <ShopLayout section="staff" title="Payroll" subtitle="The same payrolls your HR and Finance staff see in the ERP. Pay is worked out from attendance, approved overtime, leave, allowances and deductions.">
    <PayrollDesk :api="hrPayroll" mode="owner" />
  </ShopLayout>
</template>

<script setup>
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import PayrollDesk from "../../../components/hr/payroll/PayrollDesk.vue";
import { hrPayroll } from "../../../services/payrollDesk";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";
</script>
