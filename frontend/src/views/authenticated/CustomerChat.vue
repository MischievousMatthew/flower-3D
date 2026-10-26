<template>
  <Chat
    chat-role="customer"
    :initial-vendor-id="initialVendorId"
    :initial-vendor-name="initialVendorName"
    :initial-about="initialAbout"
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

// "Ask the shop" from a product page or an order passes what it's about in history state
const initialAbout = (() => {
  try {
    const about = JSON.parse(window.history.state?.chatAbout ?? "null");
    return about && ["product", "order"].includes(about.type) && Number(about.id) > 0 ? about : null;
  } catch {
    return null;
  }
})();

const initialVendorName = computed(() => {
  const rawVendorName = route.query.vendor_name;
  return typeof rawVendorName === "string" ? rawVendorName : "";
});
</script>
