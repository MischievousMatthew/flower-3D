<template>
  <div class="bc-theme as" :class="{ wide }">
    <aside class="as-art" aria-hidden="true">
      <router-link to="/" class="as-brand" tabindex="-1">
        <img src="/bloomcraft-darkmode-removebg.png" alt="" width="34" height="34" />
        <span>BloomCraft</span>
      </router-link>
      <svg class="as-bloom" :class="{ draw: !reduced }" viewBox="0 0 320 420" fill="none" stroke="currentColor" stroke-width="1.15" stroke-linecap="round" stroke-linejoin="round">
        <g transform="translate(160 150)">
          <path v-for="(p, i) in PETALS" :key="i" :d="p.d" :transform="`rotate(${p.r})`" :style="{ '--i': i }" />
          <circle r="7" style="--i: 14" />
          <circle r="2.5" style="--i: 15" />
        </g>
        <path d="M160 214 C 158 270, 166 330, 156 410" style="--i: 16" />
        <path d="M160 300 C 190 280, 222 284, 240 300 C 214 318, 184 316, 160 300 Z" style="--i: 17" />
        <path d="M160 300 L 222 298" style="--i: 18" />
        <path d="M158 350 C 128 334, 98 340, 82 356 C 110 370, 138 366, 158 350 Z" style="--i: 19" />
        <path d="M158 350 L 100 354" style="--i: 20" />
      </svg>
      <figcaption class="as-caption">
        <slot name="caption">
          <i>Rosa</i> “Petal &amp; Stem”<br />
          hand-tied by a local florist, delivered the morning it's made.
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

defineProps({
  title: { type: String, required: true },
  lead: { type: String, default: "" },
  // The vendor application needs room for maps and uploads
  wide: { type: Boolean, default: false },
});

const reduced = typeof window !== "undefined" && window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;

// Twelve petals in three rings, each a little smaller and turned
const petal = (len, w) => `M0 0 C ${w} ${-len * 0.35}, ${w * 0.8} ${-len * 0.85}, 0 ${-len} C ${-w * 0.8} ${-len * 0.85}, ${-w} ${-len * 0.35}, 0 0 Z`;
const PETALS = [
  ...[0, 72, 144, 216, 288].map((r) => ({ r, d: petal(96, 46) })),
  ...[36, 108, 180, 252, 324].map((r) => ({ r, d: petal(70, 34) })),
  ...[0, 120, 240].map((r) => ({ r: r + 18, d: petal(40, 20) })),
];
</script>

<style scoped>
.as {
  min-height: 100vh;
  display: grid;
  grid-template-columns: minmax(340px, 5fr) 7fr;
  background: var(--paper);
}
.as.wide {
  grid-template-columns: minmax(300px, 4fr) 9fr;
}

/* Specimen panel */
.as-art {
  position: sticky;
  top: 0;
  height: 100vh;
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
  animation-delay: calc(var(--i) * 70ms);
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
  margin: 0 0 10px;
  font-size: clamp(38px, 4vw, 48px);
}
.as-lead {
  margin: 0 0 32px;
  max-width: 46ch;
  font-size: 15px;
  line-height: 1.65;
  color: var(--ink-soft);
}

@media (max-width: 960px) {
  .as,
  .as.wide {
    grid-template-columns: 1fr;
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
