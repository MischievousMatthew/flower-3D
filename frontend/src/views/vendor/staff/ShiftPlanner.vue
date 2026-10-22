<template>
  <ShopLayout section="staff" title="Shift planner" subtitle="The same planner your HR staff use in the ERP. Create shifts, drag people onto them, and have someone cover a no-show.">
    <ShiftBoard :api="api" />
  </ShopLayout>
</template>

<script setup>
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import ShiftBoard from "../../../components/hr/schedules/ShiftBoard.vue";
import { scheduleApi } from "../../../services/scheduleApi";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";

// The shop owner can do everything
const api = scheduleApi("vendor/staff/planner");
</script>
