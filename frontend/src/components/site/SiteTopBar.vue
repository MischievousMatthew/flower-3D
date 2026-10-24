<template>
  <header class="topbar">
    <div class="bc-container topbar-inner">
      <router-link to="/" class="topbar-brand">
        <img src="/bloomcraft-blankBg.png" alt="" width="34" height="34" />
        <span>{{ site.settings.site_name || "BloomCraft" }}</span>
      </router-link>
      <nav class="topbar-links" aria-label="Main">
        <router-link to="/shop">Shop</router-link>
        <router-link to="/journal">Journal</router-link>
        <router-link to="/shop" class="bc-btn bc-btn--dark topbar-cta">Start shopping</router-link>
      </nav>
    </div>
  </header>
</template>

<script setup>
import { useSiteContent } from "../../composables/useSiteContent";

/** Simple header for content pages such as the Journal */
const { site } = useSiteContent();
</script>

<style scoped>
.topbar {
  position: sticky;
  top: 0;
  z-index: 50;
  background: rgba(242, 245, 242, 0.88);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--line-soft);
}
.topbar-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  height: 72px;
}
.topbar-brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--ink);
  text-decoration: none;
  font-family: var(--font-display);
  font-size: 26px;
}
.topbar-links {
  display: flex;
  align-items: center;
  gap: 26px;
  font-size: 14px;
}
.topbar-links a:not(.topbar-cta) {
  color: var(--ink);
  text-decoration: none;
}
.topbar-links a.router-link-active:not(.topbar-cta) {
  color: var(--leaf);
}
.topbar-cta {
  padding: 10px 18px;
  font-size: 13px;
}
@media (max-width: 560px) {
  .topbar-cta {
    display: none;
  }
}
</style>
