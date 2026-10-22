<template>
  <HrPage title="Shifts & schedules" subtitle="Create shifts, link people to them, and drag staff from the right onto a shift. If someone doesn't show up, drag a free person onto their name to cover.">
    <div v-if="!perms.view" class="sp-card">
      <div class="sp-empty">
        <span class="sp-empty-icon" v-html="ICONS.lock"></span>
        <h3>You can't see schedules</h3>
        <p>Ask the shop owner to give your role the Schedules permission.</p>
      </div>
    </div>
    <ShiftBoard v-else :api="api" :perms="perms" />
  </HrPage>
</template>

<script setup>
import { computed } from "vue";
import HrPage from "../../../../components/hr/HrPage.vue";
import ShiftBoard from "../../../../components/hr/schedules/ShiftBoard.vue";
import { SHOP_ICONS as ICONS } from "../../../../components/vendor/shop/shopIcons";
import { useAssignment } from "../../../../composables/useAssignment";
import { scheduleApi } from "../../../../services/scheduleApi";

const { can } = useAssignment();
const perms = computed(() => ({
  view: can("schedules", "view"),
  create: can("schedules", "create"),
  edit: can("schedules", "edit"),
  delete: can("schedules", "delete"),
}));
const api = scheduleApi("/schedules");
</script>
