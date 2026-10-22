<template>
  <HrPage title="Attendance reports" subtitle="Attendance rate, late arrivals, early leaves and absences per person. Download as CSV for payroll or records." :tabs="ATTENDANCE_TABS">
    <AttendanceReport :fetch="attendanceApi.report" />
  </HrPage>
</template>

<script setup>
import HrPage from "../../../../components/hr/HrPage.vue";
import AttendanceReport from "../../../../components/hr/attendance/AttendanceReport.vue";
import { ATTENDANCE_TABS } from "../../../../components/hr/hrUi";
import attendanceApi from "../../../../services/attendanceApi";
</script>
