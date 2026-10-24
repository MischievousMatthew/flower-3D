<template>
  <div class="eshell">
    <ErpSidebar />
    <!-- The header sits inside the content column so the fixed sidebar never covers it -->
    <div class="eshell-col">
      <ErpHeader />
      <main class="eshell-main">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
// One workspace for HR and Finance, laid out like the vendor portal. Colors
// come from the --v-* tokens, so everything follows the ERP light/dark switch.
import ErpSidebar from "./ErpSidebar.vue";
import ErpHeader from "./ErpHeader.vue";
</script>

<style scoped>
.eshell {
  min-height: 100vh;
  background: var(--v-bg);
  color: var(--v-text);
  font-family: "Poppins", sans-serif;
}
.eshell-col {
  min-width: 0;
  margin-left: 260px;
}
.eshell-main {
  min-width: 0;
  padding: 22px 32px 64px;
}
@media (max-width: 968px) {
  .eshell-col {
    margin-left: 0;
  }
  .eshell-main {
    padding: 16px 16px 48px;
  }
}
</style>
