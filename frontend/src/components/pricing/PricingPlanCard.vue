<template>
  <article class="pricing-plan" :class="{ 'pricing-plan--featured': plan.featured, 'pricing-plan--landing': variant === 'landing' }">
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

    <button type="button" class="plan-action" :class="{ 'plan-action--dark': plan.featured }" :disabled="loading" @click="$emit('select', plan)">
      {{ actionLabel || plan.action }}
    </button>
  </article>
</template>

<script setup>
defineProps({
  plan: {
    type: Object,
    required: true,
  },
  loading: Boolean,
  actionLabel: String,
  // "landing" = larger glass card for the landing page's horizontal track.
  variant: {
    type: String,
    default: "default",
  },
});

defineEmits(['select']);
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
.plan-action { display: block; width: 100%; margin-top: auto; padding: 12px 18px; color: var(--ink, #1e1b18); background: transparent; border: 1px solid var(--line, rgba(30, 27, 24, 0.12)); border-radius: 999px; font: inherit; font-size: 13.5px; font-weight: 600; text-align: center; text-decoration: none; cursor: pointer; transition: all 0.25s ease; }
.plan-action:hover { border-color: var(--ink, #1e1b18); transform: translateY(-2px); }
.plan-action:disabled { cursor: wait; opacity: .65; transform: none; }
.plan-action--dark { color: var(--ivory, #faf8f5); background: var(--ink, #1e1b18); border-color: var(--ink, #1e1b18); }
.plan-action--dark:hover { background: var(--rosewood, #b86558); border-color: var(--rosewood, #b86558); }

/* Landing-page variant: editorial card; the featured plan inverts to espresso. */
.pricing-plan--landing { padding: 44px 34px 34px; background: #faf8f4; border: 1px solid rgba(30, 27, 24, 0.1); border-radius: 18px; box-shadow: none; }
.pricing-plan--landing .plan-badge { top: 26px; right: 26px; left: auto; padding: 0; background: none; color: #d4b27a; font-size: 10.5px; font-weight: 500; letter-spacing: 0.2em; }
.pricing-plan--landing .plan-name { margin-bottom: 22px; color: #8e443a; font-size: 11.5px; font-weight: 500; letter-spacing: 0.22em; }
.pricing-plan--landing .plan-price { font-family: "Cormorant Garamond", Georgia, serif; font-size: clamp(46px, 3.6vw, 58px); font-weight: 500; line-height: 1; letter-spacing: -0.02em; }
.pricing-plan--landing .plan-price small { font-family: "Poppins", sans-serif; font-size: 13px; font-weight: 400; }
.pricing-plan--landing .plan-description { min-height: 72px; margin: 18px 0 26px; font-size: 14.5px; }
.pricing-plan--landing .plan-highlights { gap: 14px; padding-top: 26px; border-top: 1px solid rgba(30, 27, 24, 0.1); font-size: 14px; }
.pricing-plan--landing .plan-highlights span { color: #b86558; font-weight: 400; }
.pricing-plan--landing .plan-action { padding: 15px 20px; font-size: 14px; font-weight: 500; }
.pricing-plan--landing .plan-action:focus-visible { outline: 2px solid var(--rosewood, #b86558); outline-offset: 3px; }
.pricing-plan--landing.pricing-plan--featured { background: #1e1b18; border-color: #1e1b18; color: #f4f1ec; box-shadow: 0 30px 70px rgba(30, 27, 24, 0.2); }
.pricing-plan--landing.pricing-plan--featured .plan-price { color: #f4f1ec; }
.pricing-plan--landing.pricing-plan--featured .plan-price small,
.pricing-plan--landing.pricing-plan--featured .plan-description { color: rgba(244, 241, 236, 0.66); }
.pricing-plan--landing.pricing-plan--featured .plan-name { color: #d4b27a; }
.pricing-plan--landing.pricing-plan--featured .plan-highlights { color: #f4f1ec; border-top-color: rgba(244, 241, 236, 0.14); }
.pricing-plan--landing.pricing-plan--featured .plan-highlights span { color: #d4b27a; }
.pricing-plan--landing.pricing-plan--featured .plan-action { color: #1e1b18; background: #f4f1ec; border-color: #f4f1ec; }
.pricing-plan--landing.pricing-plan--featured .plan-action:hover { background: #d4b27a; border-color: #d4b27a; }
</style>
