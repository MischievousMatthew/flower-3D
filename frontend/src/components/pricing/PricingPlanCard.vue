<template>
  <article class="pricing-plan" :class="{ 'pricing-plan--featured': plan.featured }">
    <span v-if="plan.featured" class="plan-badge">Most popular</span>
    <p class="plan-name">{{ plan.name }}</p>
    <div class="plan-price">
      <span>{{ plan.price }}</span>
      <small v-if="plan.priceSuffix">{{ plan.priceSuffix }}</small>
    </div>
    <p class="plan-description">{{ plan.description }}</p>

    <ul class="plan-highlights">
      <li v-for="highlight in plan.highlights" :key="highlight">
        <span aria-hidden="true">✓</span>{{ highlight }}
      </li>
    </ul>

    <router-link to="/guest/vendor_register" class="plan-action" :class="{ 'plan-action--dark': plan.featured }">
      {{ plan.action }}
    </router-link>
  </article>
</template>

<script setup>
defineProps({
  plan: {
    type: Object,
    required: true,
  },
});
</script>

<style scoped>
.pricing-plan {
  position: relative;
  display: flex;
  min-height: 100%;
  flex-direction: column;
  padding: 34px 28px 28px;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid var(--line, rgba(30, 27, 24, 0.12));
  border-radius: 22px;
  box-shadow: 0 12px 34px rgba(30, 27, 24, 0.05);
}

.pricing-plan--featured {
  border-color: var(--rosewood, #b86558);
  box-shadow: 0 18px 42px rgba(184, 101, 88, 0.16);
}

.plan-badge {
  position: absolute;
  top: -13px;
  left: 24px;
  padding: 5px 13px;
  color: var(--ivory, #faf8f5);
  background: var(--rosewood, #b86558);
  border-radius: 999px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}

.plan-name {
  margin: 0 0 16px;
  color: var(--rosewood, #b86558);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.12em;
}

.plan-price { color: var(--ink, #1e1b18); font-size: clamp(28px, 2.4vw, 36px); font-weight: 700; letter-spacing: -0.04em; }
.plan-price small { margin-left: 4px; color: var(--ink-soft, #6c655f); font-size: 13px; font-weight: 500; letter-spacing: 0; }
.plan-description { min-height: 50px; margin: 15px 0 24px; color: var(--ink-soft, #6c655f); font-size: 14px; line-height: 1.65; }
.plan-highlights { display: grid; gap: 12px; margin: 0 0 28px; padding: 0; list-style: none; color: var(--ink, #1e1b18); font-size: 13.5px; line-height: 1.45; }
.plan-highlights li { display: flex; gap: 9px; }
.plan-highlights span { color: var(--rosewood, #b86558); font-weight: 700; }
.plan-action { display: block; margin-top: auto; padding: 12px 18px; color: var(--ink, #1e1b18); border: 1px solid var(--line, rgba(30, 27, 24, 0.12)); border-radius: 999px; font-size: 13.5px; font-weight: 600; text-align: center; text-decoration: none; transition: all 0.25s ease; }
.plan-action:hover { border-color: var(--ink, #1e1b18); transform: translateY(-2px); }
.plan-action--dark { color: var(--ivory, #faf8f5); background: var(--ink, #1e1b18); border-color: var(--ink, #1e1b18); }
.plan-action--dark:hover { background: var(--rosewood, #b86558); border-color: var(--rosewood, #b86558); }
</style>
