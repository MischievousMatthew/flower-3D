<template>
  <ShopLayout section="staff" title="Leave & overtime" subtitle="The same requests your HR staff see in the ERP. Approve leave and overtime, check balances, set leave rules, and print the leave QR poster.">
    <LeaveDesk :api="leaveDesk" />
  </ShopLayout>
</template>

<script setup>
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import LeaveDesk from "../../../components/hr/leave/LeaveDesk.vue";
import { leaveDesk } from "../../../services/leaveDesk";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";
</script>
