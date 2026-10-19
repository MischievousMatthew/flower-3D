<template>
  <router-view />
  <CheckoutPausedModal />
  <SessionReplacedModal />
</template>

<script setup>
import { onMounted } from "vue";
import CheckoutPausedModal from "./components/site/CheckoutPausedModal.vue";
import SessionReplacedModal from "./components/site/SessionReplacedModal.vue";
import { startSessionWatch } from "./composables/useSessionWatch";
import { useRoute } from "vue-router";
import { useAuth } from "./composables/useAuth";
import { useInactivityTimeout } from "./composables/useInactivityTimeout";
import "vue3-toastify/dist/index.css";

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
