<template>
  <Chat
    chat-role="customer"
    :initial-vendor-id="initialVendorId"
    :initial-vendor-name="initialVendorName"
  />
</template>

<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import Chat from "../../layouts/Chat/Chat.vue";

const route = useRoute();

const initialVendorId = computed(() => {
  const rawVendorId = route.query.vendor_id;
  const parsedVendorId = Number.parseInt(rawVendorId, 10);

  return Number.isFinite(parsedVendorId) ? parsedVendorId : null;
});

const initialVendorName = computed(() => {
  const rawVendorName = route.query.vendor_name;
  return typeof rawVendorName === "string" ? rawVendorName : "";
});
</script>
