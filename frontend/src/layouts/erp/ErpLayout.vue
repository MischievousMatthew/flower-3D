<template>
  <ErpShell>
    <!-- Pages not redesigned yet (Procurement, Supply chain) sit on a light panel so they stay readable in dark mode -->
    <div v-if="legacy" class="erp-legacy"><router-view /></div>
    <router-view v-else />
  </ErpShell>
</template>

<script setup>
// The route layout for every ERP section. They all use this one file, so
// moving between them keeps the same sidebar and header instead of rebuilding them.
import { computed } from "vue";
import { useRoute } from "vue-router";
import ErpShell from "./ErpShell.vue";

const route = useRoute();
const legacy = computed(() => route.matched.some((r) => r.meta?.erpLegacy));
</script>

<style scoped>
.erp-legacy {
  min-height: calc(100vh - 160px);
  border-radius: 18px;
  overflow: hidden;
  background: #f8fafc;
  color: #1a202c;
  color-scheme: light;
  padding: 24px 28px;
}
@media (max-width: 968px) {
  .erp-legacy {
    padding: 16px;
  }
}
</style>
