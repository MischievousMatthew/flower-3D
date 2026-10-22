<template>
  <HrPage title="Leave & overtime" subtitle="Approve leave and overtime requests, track everyone's leave balances, set the shop's leave rules, and print the leave QR poster.">
    <div v-if="!perms.view" class="sp-card">
      <div class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.lock"></span>
        <h3>You can't see leave</h3>
        <p>Ask the shop owner to give your role the Leave permission.</p>
      </div>
    </div>
    <LeaveDesk v-else :api="leaveDesk" :perms="perms" />
  </HrPage>
</template>

<script setup>
import { computed } from "vue";
import HrPage from "../../../../components/hr/HrPage.vue";
import LeaveDesk from "../../../../components/hr/leave/LeaveDesk.vue";
import { SHOP_ICONS as ICONS } from "../../../../components/vendor/shop/shopIcons";
import { useAssignment } from "../../../../composables/useAssignment";
import { leaveDesk } from "../../../../services/leaveDesk";

const { can } = useAssignment();
const perms = computed(() => Object.fromEntries(["view", "create", "edit", "approve", "reject", "delete"].map((p) => [p, can("leave_management", p)])));
</script>
