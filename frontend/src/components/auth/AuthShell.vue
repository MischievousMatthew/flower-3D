<template>
  <div class="bc-theme as" :class="{ wide }">
    <aside class="as-art" aria-hidden="true">
      <router-link to="/" class="as-brand" tabindex="-1">
        <img src="/bloomcraft-darkmode-removebg.png" alt="" width="34" height="34" />
        <span>BloomCraft</span>
      </router-link>
      <svg :key="flower.key" class="as-bloom" :class="{ draw: !reduced }" viewBox="0 0 320 420" fill="none" stroke="currentColor" stroke-width="1.15" stroke-linecap="round" stroke-linejoin="round">
        <template v-for="(s, i) in flower.shapes" :key="i">
          <path v-if="s.d" :d="s.d" :transform="s.transform" :style="{ '--i': i }" />
          <circle v-else :cx="s.cx" :cy="s.cy" :r="s.r" :style="{ '--i': i }" />
        </template>
      </svg>
      <figcaption class="as-caption">
        <slot name="caption">
          <i>{{ flower.latin }}</i> “{{ flower.name }}”<br />
          {{ flower.line }}
        </slot>
      </figcaption>
    </aside>

    <main class="as-main">
      <header class="as-top">
        <router-link to="/" class="as-home">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
          Back to BloomCraft
        </router-link>
        <div class="as-top-right"><slot name="top" /></div>
      </header>
      <div class="as-col">
        <router-link to="/" class="as-mobile-brand">
          <img src="/bloomcraft-blankBg.png" alt="BloomCraft" width="44" height="44" />
        </router-link>
        <h1 class="as-title">{{ title }}</h1>
        <p v-if="lead || $slots.lead" class="as-lead"><slot name="lead">{{ lead }}</slot></p>
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
// The sign-in, sign-up and password pages share this frame: a botanical
// line drawing on the left (drawn once when the page opens) and the form on
// the right, in the landing page's ivory and Cormorant style.
import "../../styles/bloomcraft.css";
import "./auth.css";
import { pickFlower } from "./flowers";

defineProps({
  title: { type: String, required: true },
  lead: { type: String, default: "" },
  // The vendor application needs room for maps and uploads
  wide: { type: Boolean, default: false },
});

const reduced = typeof window !== "undefined" && window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;

// A different flower each visit: tulip, sunflower, rose or daisy
const flower = pickFlower();
</script>

<style scoped>
/* The panel is pinned to the left of the window, so the flower stays put
   however far the form scrolls; the form sits beside it */
.as {
  --art-w: max(340px, 41.667vw);
  min-height: 100vh;
  padding-left: var(--art-w);
  background: var(--paper);
}
.as.wide {
  --art-w: max(300px, 30.77vw);
}

/* Specimen panel */
.as-art {
  position: fixed;
  top: 0;
  bottom: 0;
  left: 0;
  z-index: 1;
  width: var(--art-w);
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  padding: 40px 44px;
  overflow: hidden;
  background:
    radial-gradient(ellipse 70% 50% at 50% 38%, rgba(212, 178, 122, 0.12), transparent 70%),
    var(--espresso);
  color: var(--gold);
}
.as-brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  font-family: var(--font-display);
  font-size: 24px;
  font-weight: 500;
  color: #f4efe6;
  text-decoration: none;
}
.as-bloom {
  width: min(78%, 320px);
  margin: 0 auto;
  color: var(--gold);
}
.as-bloom.draw :is(path, circle) {
  stroke-dasharray: 700;
  stroke-dashoffset: 700;
  animation: as-draw 1.6s cubic-bezier(0.6, 0, 0.2, 1) forwards;
  animation-delay: calc(min(var(--i), 30) * 45ms);
}
@keyframes as-draw {
  to {
    stroke-dashoffset: 0;
  }
}
.as-caption {
  max-width: 32ch;
  font-family: var(--font-display);
  font-size: 19px;
  line-height: 1.45;
  color: rgba(244, 239, 230, 0.72);
}
.as-caption :deep(i) {
  color: var(--gold);
}

/* Form side */
.as-main {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  min-width: 0;
  padding: 28px clamp(20px, 6vw, 88px) 56px;
}
.as-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  font-size: 14px;
}
.as-home {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--ink-soft);
  text-decoration: none;
}
.as-home:hover {
  color: var(--ink);
}
.as-home svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
}
.as-top-right {
  color: var(--ink-soft);
}
.as-col {
  width: 100%;
  max-width: 420px;
  margin: auto 0;
  padding-top: 40px;
}
.wide .as-col {
  max-width: 780px;
}
.as-mobile-brand {
  display: none;
}
.as-title {
  margin: 0 0 12px;
  font-size: clamp(34px, 3.2vw, 44px);
}
.as-lead {
  margin: 0 0 24px;
  max-width: 46ch;
  font-size: 14.5px;
  line-height: 1.65;
  color: var(--ink-soft);
}

@media (max-width: 960px) {
  .as,
  .as.wide {
    padding-left: 0;
  }
  .as-art {
    display: none;
  }
  .as-mobile-brand {
    display: inline-block;
    margin-bottom: 18px;
  }
  .as-col {
    margin: 0 auto;
    padding-top: 24px;
  }
}
@media (max-width: 520px) {
  .as-main {
    padding: 18px 16px 40px;
  }
  .as-top-right {
    display: none;
  }
}
</style>
