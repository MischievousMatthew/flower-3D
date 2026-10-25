<template>
  <router-view />
  <CheckoutPausedModal />
  <SessionReplacedModal />
  <SessionExpiredModal />
  <!-- Messages for everything (at most two at a time) -->
  <SnackbarHost />
</template>

<script setup>
import { onMounted } from "vue";
import CheckoutPausedModal from "./components/site/CheckoutPausedModal.vue";
import SessionReplacedModal from "./components/site/SessionReplacedModal.vue";
import SessionExpiredModal from "./components/site/SessionExpiredModal.vue";
import SnackbarHost from "./components/site/SnackbarHost.vue";
import { startSessionWatch } from "./composables/useSessionWatch";
import { useRoute } from "vue-router";
import { useAuth } from "./composables/useAuth";
import { useInactivityTimeout } from "./composables/useInactivityTimeout";

const { initAuth } = useAuth();
const route = useRoute();

// Initialize the 15-minute global inactivity security timeout
useInactivityTimeout(15);

onMounted(() => {
  // Notice within a minute if this account signs in on another browser
  startSessionWatch();

  if (route.meta?.public) {
    return;
  }

  initAuth();
});
</script>
