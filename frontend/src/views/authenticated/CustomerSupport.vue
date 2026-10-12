<template>
  <div class="cs-page bc-theme">
    <NavHeader :cartCount="cartCount" :isAuthenticated="true" />
    <main class="bc-container cs-main">
      <SupportCenter theme="shop" orders-endpoint="/customer/orders" />
    </main>
    <SiteFooter />
  </div>
</template>

<script setup>
import { computed, onMounted } from "vue";
import NavHeader from "../../layouts/NavHeader.vue";
import SupportCenter from "../../components/support/SupportCenter.vue";
import SiteFooter from "../../components/site/SiteFooter.vue";
import { useCart } from "../../composables/useCart";
import "../../styles/bloomcraft.css";

const cartStore = useCart();
const cartCount = computed(() => cartStore.count.value);
onMounted(() => cartStore.initialize());
</script>

<style scoped>
.cs-page {
  min-height: 100vh;
  background: var(--ivory);
}
:global(body) {
  margin: 0;
}
.cs-main {
  padding-top: 130px;
  padding-bottom: 40px;
}
</style>
