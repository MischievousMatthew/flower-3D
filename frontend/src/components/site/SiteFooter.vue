<template>
  <footer class="site-footer">
    <div class="bc-container footer-grid">
      <div class="footer-brand">
        <span class="footer-name">{{ settings.site_name || "BloomCraft" }}</span>
        <p>{{ settings.about || settings.tagline || "Your marketplace for custom flower arrangements." }}</p>
        <ul v-if="socials.length" class="footer-socials">
          <li v-for="s in socials" :key="s.key">
            <a :href="s.url" target="_blank" rel="noopener">{{ s.label }}</a>
          </li>
        </ul>
      </div>

      <div v-if="hasContact" class="footer-col">
        <h4>Contact us</h4>
        <ul>
          <li v-if="settings.contact_email"><a :href="`mailto:${settings.contact_email}`">{{ settings.contact_email }}</a></li>
          <li v-if="settings.contact_phone"><a :href="`tel:${settings.contact_phone.replace(/\s+/g, '')}`">{{ settings.contact_phone }}</a></li>
          <li v-if="settings.address">{{ settings.address }}</li>
          <li v-if="settings.business_hours">{{ settings.business_hours }}</li>
        </ul>
      </div>

      <div class="footer-col">
        <h4>Explore</h4>
        <ul>
          <li><router-link to="/shop">Shop</router-link></li>
          <li><router-link to="/journal">Journal</router-link></li>
          <li><router-link to="/pricing">Sell on {{ settings.site_name || "BloomCraft" }}</router-link></li>
        </ul>
      </div>
    </div>
    <div class="bc-container footer-bottom">
      <p>© {{ year }} {{ settings.site_name || "BloomCraft" }}. {{ settings.footer_note || "All rights reserved." }}</p>
    </div>
  </footer>
</template>

<script setup>
import { computed } from "vue";
import { useSiteContent } from "../../composables/useSiteContent";

/** Footer with the website information from Admin → Website */
const { site } = useSiteContent();
const settings = computed(() => site.settings ?? {});
const SOCIAL_LABELS = { facebook: "Facebook", instagram: "Instagram", tiktok: "TikTok", x: "X" };
const socials = computed(() =>
  Object.entries(settings.value.socials ?? {})
    .filter(([, url]) => url)
    .map(([key, url]) => ({ key, url, label: SOCIAL_LABELS[key] ?? key })),
);
const hasContact = computed(() => ["contact_email", "contact_phone", "address", "business_hours"].some((k) => settings.value[k]));
const year = new Date().getFullYear();
</script>

<style scoped>
.site-footer {
  margin-top: clamp(40px, 6vw, 80px);
  padding: clamp(56px, 7vw, 96px) 0 28px;
  background: var(--espresso);
  color: rgba(250, 251, 250, 0.78);
}
.footer-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.6fr) repeat(2, minmax(0, 1fr));
  gap: 40px;
}
.footer-name {
  display: block;
  font-family: var(--font-display);
  font-size: 34px;
  color: var(--paper);
  margin-bottom: 12px;
}
.footer-brand p {
  max-width: 380px;
  margin: 0 0 18px;
  font-size: 14px;
  line-height: 1.7;
}
.footer-socials {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
  margin: 0;
  padding: 0;
  list-style: none;
}
.footer-col h4 {
  margin: 0 0 14px;
  color: var(--paper);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}
.footer-col ul {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 9px;
  font-size: 14px;
}
.site-footer a {
  color: rgba(250, 251, 250, 0.85);
  text-decoration: none;
}
.site-footer a:hover {
  color: var(--leaf-light);
}
.footer-bottom {
  margin-top: 48px;
  padding-top: 20px;
  border-top: 1px solid rgba(250, 251, 250, 0.12);
  font-size: 12.5px;
}
.footer-bottom p {
  margin: 0;
}
@media (max-width: 760px) {
  .footer-grid {
    grid-template-columns: minmax(0, 1fr);
    gap: 28px;
  }
}
</style>
