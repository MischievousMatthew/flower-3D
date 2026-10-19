<template>
  <section class="ts" :class="`ts--${variant}`" :aria-label="variant === 'top' ? 'Why shop with us' : 'Our promise'">
    <div class="bc-container">
      <ul class="ts-list">
        <li v-for="item in items" :key="item.title" class="ts-item">
          <span class="ts-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path v-for="(d, i) in item.icon" :key="i" :d="d" /></svg>
          </span>
          <span class="ts-copy">
            <strong>{{ item.title }}</strong>
            <small>{{ item.text }}</small>
          </span>
        </li>
      </ul>
    </div>
  </section>
</template>

<script setup>
import { computed } from "vue";

/** The promise strips under the hero ("top") and above the footer ("bottom") */
const props = defineProps({ variant: { type: String, default: "top" } });

const ICONS = {
  flower: ["M12 7.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 1 1 0 5z", "M16.5 12a2.5 2.5 0 1 1 5 0 2.5 2.5 0 1 1-5 0z", "M12 16.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 1 1 0-5z", "M7.5 12a2.5 2.5 0 1 1-5 0 2.5 2.5 0 1 1 5 0z", "M12 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"],
  shop: ["M4 21V10l8-6 8 6v11", "M9 21v-6h6v6", "M3 10h18"],
  truck: ["M3 6h11v10H3z", "M14 10h4l3 3v3h-7", "M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z", "M17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"],
  card: ["M3 6h18v12H3z", "M3 10h18", "M7 15h3"],
  returns: ["M4 12a8 8 0 1 0 2.4-5.7L4 8.5", "M4 4v4.5h4.5"],
  heart: ["M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"],
};

const items = computed(() =>
  props.variant === "top"
    ? [
        { title: "Fresh flowers", text: "Premium quality blooms", icon: ICONS.flower },
        { title: "Local shops", text: "Support small businesses", icon: ICONS.shop },
        { title: "Same-day delivery", text: "For selected areas", icon: ICONS.truck },
        { title: "Secure payment", text: "Safe and reliable", icon: ICONS.card },
        { title: "Easy returns", text: "Hassle-free process", icon: ICONS.returns },
      ]
    : [
        { title: "Fresh & premium flowers", text: "Handpicked daily for the best quality.", icon: ICONS.heart },
        { title: "Support local florists", text: "Empowering small businesses.", icon: ICONS.shop },
        { title: "Secure payment", text: "Safe and reliable transactions.", icon: ICONS.card },
        { title: "Fast & reliable delivery", text: "On time, every time.", icon: ICONS.truck },
      ],
);
</script>

<style scoped>
.ts,
.ts * {
  box-sizing: border-box;
}

.ts--top {
  position: relative;
  z-index: 2;
  margin-top: 22px;
}

.ts--bottom {
  /* Sits right on the footer (which adds its own space above) */
  margin-top: clamp(32px, 4vw, 56px);
  margin-bottom: calc(-1 * clamp(40px, 6vw, 80px));
  padding: 22px 0;
  border-top: 1px solid var(--line-soft);
  background: var(--ivory-deep);
}

.ts-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
  margin: 0;
  padding: 0;
  list-style: none;
}

.ts--top .ts-list {
  padding: 18px 8px;
  border: 1px solid var(--line-soft);
  border-radius: 16px;
  background: var(--paper);
  box-shadow: 0 16px 40px -30px rgba(30, 27, 24, 0.35);
}

.ts-item {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 14px;
  padding: 8px 18px;
}

.ts--top .ts-item + .ts-item {
  border-left: 1px solid var(--line-soft);
}

.ts-icon {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: rgba(184, 101, 88, 0.1);
}

.ts--bottom .ts-icon {
  background: transparent;
}

.ts-icon svg {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: var(--rosewood-dark);
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.ts-copy {
  display: grid;
  gap: 2px;
}

.ts-copy strong {
  font-size: 13px;
  font-weight: 500;
  color: var(--ink);
}

.ts-copy small {
  font-size: 11.5px;
  color: var(--ink-soft);
}

@media (max-width: 900px) {
  .ts--top .ts-list {
    display: flex;
    overflow-x: auto;
    scrollbar-width: none;
  }

  .ts--top .ts-item {
    flex: 0 0 auto;
    justify-content: flex-start;
  }

  .ts--bottom .ts-list {
    grid-template-columns: 1fr 1fr;
    gap: 8px 0;
  }

  .ts--bottom .ts-item {
    justify-content: flex-start;
    padding: 8px 4px;
  }
}
</style>
