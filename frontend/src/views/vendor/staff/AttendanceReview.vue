<template>
  <ShopLayout section="staff" title="Attendance review" subtitle="Doubtful scans and correction requests wait here. They count for payroll only after you approve them.">
    <template #actions>
      <router-link :to="{ name: 'VendorStaffAttendanceDevices' }" class="sp-btn sp-btn-ghost ar-link"><span v-html="ICONS.lock"></span>Devices &amp; location</router-link>
    </template>
    <AttendanceReviewQueue :api="queueApi" />
  </ShopLayout>
</template>

<script setup>
import api from "../../../plugins/axios";
import ShopLayout from "../../../components/vendor/shop/ShopLayout.vue";
import AttendanceReviewQueue from "../../../components/hr/attendance/AttendanceReviewQueue.vue";
import { SHOP_ICONS as ICONS } from "../../../components/vendor/shop/shopIcons";
import "../../../components/vendor/shop/shop.css";
import "../../../components/vendor/staff/staff.css";

// The shop owner can always decide
const queueApi = {
  load: async () => (await api.get("vendor/staff/attendance/review")).data.data,
  decide: async (id, decision, note) => (await api.post(`vendor/staff/attendance/review/${id}`, { decision, note })).data,
  decideCorrection: async (id, decision, note) => (await api.post(`vendor/staff/attendance/corrections/${id}`, { decision, note })).data,
  decideOvertime: async (id, decision, minutes, note) => (await api.post(`vendor/staff/attendance/overtime/${id}`, { decision, minutes, note })).data,
  evidence: async (id, which, index) => (await api.get(`vendor/staff/attendance/${id}/evidence/${which}/${index}`)).data.data.url,
};
</script>

<style scoped>
.ar-link {
  text-decoration: none;
}
</style>
