<template>
  <router-view />
</template>

<script setup>
import { onMounted } from "vue";
import { useAuth } from "./composables/useAuth";
import { useInactivityTimeout } from "./composables/useInactivityTimeout";
import "vue3-toastify/dist/index.css";

const { initAuth } = useAuth();

// Initialize the 15-minute global inactivity security timeout
useInactivityTimeout(15);

onMounted(() => {
  initAuth();
});
</script>
