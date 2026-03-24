<template>
  <router-view />
</template>

<script setup>
import { onMounted } from "vue";
import { useAuth } from "./composables/useAuth";
import "vue3-toastify/dist/index.css";

const { initAuth } = useAuth();

onMounted(() => {
  initAuth();
});
</script>
