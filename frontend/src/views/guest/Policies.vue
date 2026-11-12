<template>
  <div class="policies-page bc-theme">
    <NavHeader />

    <main class="bc-container pol">
      <header class="pol-hero">
        <p class="bc-label">{{ name }}</p>
        <h1>About us &amp; <em>our policies</em></h1>
        <p class="pol-intro">How {{ name }} works, and what you can expect when you order from a shop here.</p>
      </header>

      <div class="pol-layout">
        <nav class="pol-nav" aria-label="On this page">
          <a v-for="s in SECTIONS" :key="s.id" :href="`#${s.id}`" :class="{ on: activeId === s.id }" @click.prevent="jump(s.id)">{{ s.title }}</a>
        </nav>

        <div class="pol-body">
          <section v-for="s in SECTIONS" :id="s.id" :key="s.id" class="pol-section">
            <h2>{{ s.title }}</h2>
            <p v-for="(line, i) in s.text" :key="i">{{ line.replaceAll("{name}", name) }}</p>
          </section>
          <section class="pol-section">
            <h2>Still have a question?</h2>
            <p>Our team reads every message.</p>
            <router-link class="bc-btn bc-btn--dark" :to="{ name: 'CustomerSupport' }">Contact support</router-link>
          </section>
        </div>
      </div>
    </main>

    <SiteFooter />
  </div>
</template>

<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import NavHeader from "../../layouts/NavHeader.vue";
import SiteFooter from "../../components/site/SiteFooter.vue";
import { useSiteContent } from "../../composables/useSiteContent";
import "../../styles/bloomcraft.css";

/** About us and the store policies, linked from the footer (/policies#returns etc.) */
const { site } = useSiteContent();
const route = useRoute();
const router = useRouter();
const name = computed(() => site.settings?.site_name || "BloomCraft");

const SECTIONS = [
  {
    id: "about",
    title: "About us",
    text: [
      "{name} is a marketplace for local flower shops. Every bouquet is made and delivered by a real shop near you, not a warehouse.",
      "You can buy ready-made arrangements, or design your own bouquet in 3D with the flowers, wraps and ribbons a shop has in stock.",
    ],
  },
  {
    id: "delivery",
    title: "Delivery",
    text: [
      "Each shop delivers within its own service area. The delivery fee and available dates are shown at checkout before you pay.",
      "Same-day delivery is offered by selected shops for orders placed before their cut-off time. You can follow your order on the Track my order page.",
    ],
  },
  {
    id: "payments",
    title: "Payments",
    text: [
      "You can pay with GCash, Maya, or cash on delivery where the shop offers it. Online payments are handled by our payment partner; we never store your card or wallet details.",
    ],
  },
  {
    id: "returns",
    title: "Returns & refunds",
    text: [
      "Flowers are perishable, so returns are handled case by case. If your order arrives damaged, wrong or late, request a return or refund from your order as soon as you can, with a photo.",
      "Approved refunds go back to the payment method you used, or another way the shop arranges with you.",
    ],
  },
  {
    id: "privacy",
    title: "Privacy",
    text: [
      "We only share your name, address and phone number with the shop that fulfils your order, so it can deliver. We don't sell your information.",
      "You can update or remove your details from your profile at any time.",
    ],
  },
  {
    id: "terms",
    title: "Terms of use",
    text: [
      "By ordering on {name} you agree to give accurate delivery details and to be reachable on the delivery date. Shops set their own prices and product details and are responsible for what they sell.",
      "We may suspend accounts that misuse the site, for example by placing fake orders.",
    ],
  },
];

const activeId = ref("about");
function jump(id) {
  activeId.value = id;
  router.replace({ hash: `#${id}` });
}
async function scrollToHash() {
  const id = route.hash.replace("#", "");
  if (!id) return;
  activeId.value = id;
  await nextTick();
  document.getElementById(id)?.scrollIntoView({ behavior: "smooth", block: "start" });
}
onMounted(scrollToHash);
watch(() => route.hash, scrollToHash);
</script>

<style scoped>
.policies-page {
  min-height: 100vh;
  background: var(--ivory);
}

:global(body) {
  margin: 0;
}

.pol-hero {
  padding: clamp(130px, 12vw, 170px) 0 clamp(28px, 4vw, 44px);
}

.pol-hero h1 {
  margin: 12px 0 10px;
  font-family: var(--font-display);
  font-size: clamp(40px, 5vw, 64px);
  font-weight: 500;
  line-height: 1.02;
  color: var(--ink);
}

.pol-hero h1 em {
  color: var(--leaf-deep);
}

.pol-intro {
  margin: 0;
  font-size: 15px;
  color: var(--ink-soft);
}

.pol-layout {
  display: grid;
  grid-template-columns: 220px minmax(0, 1fr);
  gap: 40px;
  align-items: start;
}

.pol-nav {
  position: sticky;
  top: 110px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.pol-nav a {
  display: flex;
  align-items: center;
  min-height: 40px;
  padding: 0 14px;
  border-radius: 10px;
  font-size: 14px;
  color: var(--ink-soft);
  text-decoration: none;
}

.pol-nav a:hover {
  color: var(--ink);
}

.pol-nav a.on {
  background: var(--paper);
  color: var(--ink);
  font-weight: 500;
}

.pol-body {
  display: grid;
  gap: 16px;
}

.pol-section {
  scroll-margin-top: 110px;
  padding: 28px 30px;
  border: 1px solid var(--line-soft);
  border-radius: 20px;
  background: var(--paper);
}

.pol-section h2 {
  margin: 0 0 10px;
  font-family: var(--font-display);
  font-size: 30px;
  font-weight: 500;
  color: var(--ink);
}

.pol-section p {
  max-width: 68ch;
  margin: 0 0 12px;
  font-size: 15px;
  line-height: 1.7;
  color: var(--ink-soft);
}

.pol :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 2px;
}

@media (max-width: 860px) {
  .pol-layout {
    grid-template-columns: minmax(0, 1fr);
    gap: 18px;
  }

  .pol-nav {
    position: static;
    flex-direction: row;
    overflow-x: auto;
    scrollbar-width: none;
  }

  .pol-nav a {
    flex: none;
  }

  .pol-section {
    padding: 22px 20px;
  }
}
</style>
