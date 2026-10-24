<template>
  <div class="nf-page bc-theme">
    <header class="nf-top">
      <router-link to="/" class="nf-brand" aria-label="BloomCraft home">
        <img src="../../../public/bloomcraft-blankBg.png" alt="" width="40" height="40" />
        <span>BloomCraft</span>
      </router-link>
    </header>

    <main class="nf-main">
      <h1 class="bc-sr-only">Page not found (404)</h1>

      <!-- "4 ✿ 4": the 0 is a wilted flower that perks up on hover or tap -->
      <div class="nf-code" aria-hidden="true">
        <span class="nf-digit">4</span>
        <button
          type="button"
          class="nf-flower"
          :class="{ perked }"
          tabindex="-1"
          @mouseenter="perked = true"
          @mouseleave="perked = false"
          @click="perked = !perked"
        >
          <svg viewBox="0 0 190 220">
            <defs>
              <!-- one bloom, centred on 0,0 -->
              <g id="nf-bloom">
                <ellipse class="nf-petal" cx="0" cy="-22" rx="13" ry="22" />
                <ellipse class="nf-petal" cx="0" cy="-22" rx="13" ry="22" transform="rotate(60)" />
                <ellipse class="nf-petal" cx="0" cy="-22" rx="13" ry="22" transform="rotate(120)" />
                <ellipse class="nf-petal" cx="0" cy="-22" rx="13" ry="22" transform="rotate(180)" />
                <ellipse class="nf-petal" cx="0" cy="-22" rx="13" ry="22" transform="rotate(240)" />
                <ellipse class="nf-petal" cx="0" cy="-22" rx="13" ry="22" transform="rotate(300)" />
                <circle class="nf-center" r="11" />
              </g>
            </defs>

            <!-- leaves low on the stem, the same in both poses -->
            <path class="nf-leaf" d="M70 192 C52 184 42 170 44 156 C60 162 70 174 70 192 Z" />
            <path class="nf-leaf" d="M71 172 C88 166 100 152 98 138 C84 144 73 156 71 172 Z" />

            <!-- Wilted: the stem arcs over and the head hangs face-down -->
            <g class="nf-pose nf-pose--wilted">
              <path class="nf-stem" d="M70 214 C68 160 74 112 104 96 C130 82 150 100 148 128" />
              <g class="nf-hang">
                <use href="#nf-bloom" transform="translate(148 150) scale(0.82 0.62)" />
              </g>
              <g class="nf-fall">
                <ellipse class="nf-fall-a" cx="150" cy="176" rx="6" ry="10" />
                <ellipse class="nf-fall-b" cx="138" cy="180" rx="5" ry="8" />
              </g>
            </g>

            <!-- Perked up: straight stem, head up -->
            <g class="nf-pose nf-pose--up">
              <path class="nf-stem" d="M70 214 C70 172 70 132 70 98" />
              <use href="#nf-bloom" transform="translate(70 66)" />
            </g>
          </svg>
        </button>
        <span class="nf-digit">4</span>
      </div>

      <p class="nf-label">Error 404</p>
      <h2 class="nf-title">This page has <em>wilted.</em></h2>
      <p class="nf-copy">
        The page you're looking for has been moved, removed, or never bloomed. Let's get you somewhere fresh.
      </p>

      <div class="nf-actions">
        <router-link :to="home.to" class="nf-btn nf-btn--solid">
          {{ home.label }} <span aria-hidden="true">→</span>
        </router-link>
        <button type="button" class="nf-btn nf-btn--ghost" @click="goBack">
          <span aria-hidden="true">←</span> Go back
        </button>
      </div>

      <nav class="nf-links" aria-label="Popular pages">
        <router-link :to="{ name: 'Shop' }">Shop</router-link>
        <span aria-hidden="true">·</span>
        <router-link to="/journal">Journal</router-link>
        <span aria-hidden="true">·</span>
        <router-link to="/pricing">Pricing</router-link>
      </nav>
    </main>

    <footer class="nf-foot">© {{ year }} BloomCraft. All rights reserved.</footer>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import "../../styles/bloomcraft.css";

const router = useRouter();
const { user } = useAuth();
const perked = ref(false);
const year = new Date().getFullYear();

// The main button goes to each account's own home (guests: the landing page)
const HOMES = {
  customer: { to: "/shop", label: "Back to the shop" },
  vendor: { to: "/vendor/finance-dashboard", label: "Go to my dashboard" },
  admin: { to: "/admin/dashboard", label: "Go to the admin dashboard" },
};
const home = computed(() => {
  let role = user.value?.role;
  if (!role) {
    try {
      const signedIn = localStorage.getItem("auth_token") || localStorage.getItem("vendor_token");
      role = signedIn ? JSON.parse(localStorage.getItem("user") || "null")?.role : null;
    } catch {
      role = null;
    }
  }
  return HOMES[role] || { to: "/", label: "Back to home" };
});

function goBack() {
  if (window.history.length > 1) router.back();
  else router.push(home.value.to);
}

onMounted(() => {
  document.title = "Page not found · BloomCraft";
});
</script>

<style scoped>
.nf-page,
.nf-page * {
  box-sizing: border-box;
}

:global(body) {
  margin: 0;
}

.nf-page {
  display: grid;
  grid-template-rows: auto 1fr auto;
  min-height: 100vh;
  min-height: 100dvh;
  overflow: hidden;
  background:
    radial-gradient(60% 50% at 50% 38%, rgba(77, 177, 115, 0.08) 0%, transparent 70%),
    var(--ivory);
  font-family: var(--font-sans);
  color: var(--ink);
}

.nf-top {
  padding: 26px clamp(20px, 5vw, 64px);
}

.nf-brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-family: var(--font-display);
  font-size: 24px;
  color: var(--ink);
  text-decoration: none;
}

.nf-main {
  display: grid;
  justify-items: center;
  align-content: center;
  padding: 12px 20px 40px;
  text-align: center;
}

/* 4 ✿ 4 */
.nf-code {
  display: flex;
  align-items: flex-end;
  gap: clamp(4px, 1.5vw, 18px);
  margin-bottom: 6px;
  line-height: 1;
}

.nf-digit {
  font-family: var(--font-display);
  font-size: clamp(120px, 20vw, 220px);
  font-weight: 400;
  line-height: 0.8;
  color: var(--ink);
}

.nf-flower {
  width: clamp(112px, 17vw, 190px);
  margin-bottom: -6px;
  padding: 0;
  border: 0;
  background: none;
  cursor: pointer;
  -webkit-tap-highlight-color: transparent;
}

.nf-flower svg {
  display: block;
  width: 100%;
  overflow: visible;
}

.nf-stem {
  fill: none;
  stroke: #6f7f55;
  stroke-width: 5;
  stroke-linecap: round;
}

.nf-leaf {
  fill: #8a9b6c;
}

.nf-petal {
  fill: #e4a9b2;
  stroke: #c7808c;
  stroke-width: 1.5;
}

.nf-center {
  fill: #d9a441;
}

/* Two poses that swap: wilted by default, upright on hover or tap */
.nf-pose {
  transform-box: view-box;
  transform-origin: 70px 214px;
  transition: opacity 0.45s ease, transform 0.9s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.nf-pose--up {
  opacity: 0;
  transform: rotate(18deg) scale(0.92);
}

.nf-flower.perked .nf-pose--wilted {
  opacity: 0;
  transform: rotate(-12deg);
}

.nf-flower.perked .nf-pose--up {
  opacity: 1;
  transform: none;
}

/* The hanging head sways a little from the tip of the stem */
.nf-hang {
  transform-box: view-box;
  transform-origin: 148px 128px;
  animation: nf-sway 4.5s ease-in-out infinite;
}

@keyframes nf-sway {
  0%,
  100% {
    transform: rotate(-5deg);
  }
  50% {
    transform: rotate(6deg);
  }
}

.nf-fall ellipse {
  fill: #e4a9b2;
  opacity: 0;
  transform-box: fill-box;
  transform-origin: center;
}

.nf-fall-a {
  animation: nf-fall 7s ease-in 1.5s infinite;
}

.nf-fall-b {
  animation: nf-fall 8s ease-in 4.5s infinite;
}

@keyframes nf-fall {
  0% {
    opacity: 0;
    transform: translate(0, 0) rotate(0deg);
  }
  12% {
    opacity: 0.9;
  }
  100% {
    opacity: 0;
    transform: translate(-12px, 46px) rotate(140deg);
  }
}

.nf-label {
  margin: 18px 0 8px;
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--leaf-deep);
}

.nf-title {
  margin: 0 0 12px;
  font-family: var(--font-display);
  font-size: clamp(34px, 4.6vw, 52px);
  font-weight: 400;
  line-height: 1.1;
}

.nf-title em {
  color: var(--leaf-deep);
}

.nf-copy {
  max-width: 460px;
  margin: 0 0 28px;
  font-size: 15px;
  line-height: 1.7;
  color: var(--ink-soft);
}

.nf-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 12px;
  margin-bottom: 26px;
}

.nf-btn {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  height: 48px;
  padding: 0 26px;
  border-radius: 999px;
  font-family: var(--font-sans);
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}

.nf-btn--solid {
  border: 1px solid var(--ink);
  background: var(--ink);
  color: var(--paper);
}

.nf-btn--solid:hover {
  border-color: var(--leaf-deep);
  background: var(--leaf-deep);
}

.nf-btn--ghost {
  border: 1px solid var(--line);
  background: transparent;
  color: var(--ink);
}

.nf-btn--ghost:hover {
  border-color: var(--ink);
}

.nf-links {
  display: flex;
  gap: 12px;
  font-size: 13px;
  color: var(--ink-soft);
}

.nf-links a {
  color: var(--ink-soft);
  text-decoration: none;
  border-bottom: 1px solid transparent;
}

.nf-links a:hover {
  color: var(--leaf-deep);
  border-bottom-color: currentColor;
}

.nf-foot {
  padding: 20px;
  font-size: 12px;
  text-align: center;
  color: var(--ink-soft);
}

.nf-page :focus-visible {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

@media (prefers-reduced-motion: reduce) {
  .nf-hang,
  .nf-fall ellipse {
    animation: none;
  }

  .nf-pose {
    transition: opacity 0.2s ease;
  }
}

@media (max-width: 480px) {
  .nf-actions {
    flex-direction: column;
    width: 100%;
    max-width: 320px;
  }

  .nf-btn {
    justify-content: center;
  }
}
</style>
