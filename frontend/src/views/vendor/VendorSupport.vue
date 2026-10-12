<template>
  <vendorHeader title="Help & support" />
  <div class="vs-page">
    <VendorSidebar />
    <main class="vs-main">
      <SupportCenter theme="vendor" orders-endpoint="/vendor/orders" />
    </main>
  </div>
</template>

<script setup>
import vendorHeader from "../../layouts/vendorHeader.vue";
import VendorSidebar from "../../layouts/Sidebar/VendorSidebar.vue";
import SupportCenter from "../../components/support/SupportCenter.vue";
</script>

<style scoped>
.vs-page {
  margin-left: 260px;
  min-height: 100vh;
  background: #f8f9fa;
  font-family: "Poppins", sans-serif;
  transition: margin-left 0.3s ease;
}
.vs-main {
  max-width: 1180px;
  margin: 0 auto;
  padding: 28px 32px 56px;
}
@media (max-width: 968px) {
  .vs-page {
    margin-left: 0;
  }
  .vs-main {
    padding: 20px 16px 48px;
  }
}
</style>
