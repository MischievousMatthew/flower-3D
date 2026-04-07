<template>
  <router-view />
</template>

<script setup>
import { onMounted } from "vue";
import { useRoute } from "vue-router";
import { useAuth } from "./composables/useAuth";
import { useInactivityTimeout } from "./composables/useInactivityTimeout";
import "vue3-toastify/dist/index.css";

const { initAuth } = useAuth();
const route = useRoute();

// Initialize the 15-minute global inactivity security timeout
useInactivityTimeout(15);

onMounted(() => {
  if (route.meta?.public) {
    return;
  }

  initAuth();
});
</script>
