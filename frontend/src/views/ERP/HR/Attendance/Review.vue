<template>
  <HrPage title="Attendance review" subtitle="Doubtful scans and correction requests. They count for payroll only after someone other than the person involved approves them." :tabs="ATTENDANCE_TABS">
    <AttendanceReviewQueue :api="queueApi" :can-decide="canDecide" />
  </HrPage>
</template>

<script setup>
import { computed } from "vue";
import HrPage from "../../../../components/hr/HrPage.vue";
import AttendanceReviewQueue from "../../../../components/hr/attendance/AttendanceReviewQueue.vue";
import { ATTENDANCE_TABS } from "../../../../components/hr/hrUi";
import { useAssignment } from "../../../../composables/useAssignment";
import attendanceApi from "../../../../services/attendanceApi";

const { can } = useAssignment();
const canDecide = computed(() => can("attendance", "approve"));

const queueApi = {
  load: () => attendanceApi.reviewQueue(),
  decide: (id, decision, note) => attendanceApi.decide(id, decision, note),
  decideCorrection: (id, decision, note) => attendanceApi.decideCorrection(id, decision, note),
  decideOvertime: (id, decision, minutes, note) => attendanceApi.decideOvertime(id, decision, minutes, note),
  evidence: (id, which, index) => attendanceApi.evidence(id, which, index),
};
</script>
