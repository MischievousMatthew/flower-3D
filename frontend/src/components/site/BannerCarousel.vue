<template>
  <div class="banners" :aria-roledescription="'carousel'" aria-label="Featured" @mouseenter="paused = true" @mouseleave="paused = false">
    <div class="banner-track" :style="{ transform: `translateX(-${active * 100}%)` }">
      <article
        v-for="(banner, i) in banners"
        :key="banner.id"
        class="banner"
        :aria-hidden="i !== active"
        :aria-label="`${i + 1} of ${banners.length}`"
      >
        <img class="banner-img" :src="banner.image_url" :alt="banner.title" :loading="i === 0 ? 'eager' : 'lazy'" />
        <div class="banner-shade"></div>
        <div class="banner-copy">
          <p v-if="banner.eyebrow" class="bc-label banner-eyebrow">{{ banner.eyebrow }}</p>
          <h1>{{ banner.title }}</h1>
          <p v-if="banner.subtitle" class="banner-sub">{{ banner.subtitle }}</p>
          <button
            v-if="banner.link_type"
            type="button"
            class="bc-btn banner-btn"
            :tabindex="i === active ? 0 : -1"
            @click="followLink(router, banner)"
          >
            {{ banner.button_label || "Shop now" }} <span class="bc-btn-arrow" aria-hidden="true">→</span>
          </button>
        </div>
      </article>
    </div>

    <template v-if="banners.length > 1">
      <button type="button" class="banner-arrow prev" aria-label="Previous slide" @click="go(active - 1)">‹</button>
      <button type="button" class="banner-arrow next" aria-label="Next slide" @click="go(active + 1)">›</button>
      <div class="banner-dots">
        <button
          v-for="(b, i) in banners"
          :key="b.id"
          type="button"
          :class="{ on: i === active }"
          :aria-label="`Show slide ${i + 1}`"
          :aria-current="i === active"
          @click="go(i)"
        ></button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import { useSiteContent } from "../../composables/useSiteContent";

/** Homepage banners from Admin → Website */
const props = defineProps({ banners: { type: Array, required: true } });
const router = useRouter();
const { followLink } = useSiteContent();

const active = ref(0);
const paused = ref(false);
const go = (i) => {
  const n = props.banners.length;
  active.value = ((i % n) + n) % n;
};

let timer = null;
onMounted(() => {
  const reduced = window.matchMedia?.("(prefers-reduced-motion: reduce)").matches;
  if (!reduced) timer = setInterval(() => !paused.value && props.banners.length > 1 && go(active.value + 1), 6500);
});
onBeforeUnmount(() => clearInterval(timer));
</script>

<style scoped>
.banners {
  position: relative;
  overflow: hidden;
  border-radius: 28px;
  background: var(--espresso);
  isolation: isolate;
}
.banner-track {
  display: flex;
  transition: transform 0.7s var(--ease-out);
}
.banner {
  position: relative;
  flex: 0 0 100%;
  min-height: clamp(420px, 56vw, 620px);
  display: flex;
  align-items: flex-end;
}
.banner-img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.banner-shade {
  position: absolute;
  inset: 0;
  background: linear-gradient(180deg, rgba(27, 22, 18, 0) 30%, rgba(27, 22, 18, 0.78) 100%),
    linear-gradient(90deg, rgba(27, 22, 18, 0.55) 0%, rgba(27, 22, 18, 0) 60%);
}
.banner-copy {
  position: relative;
  max-width: 640px;
  padding: clamp(28px, 5vw, 64px);
  color: var(--paper);
}
.banner-eyebrow {
  color: var(--gold);
}
.banner-copy h1 {
  margin: 16px 0 14px;
  color: var(--paper);
  font-size: clamp(40px, 5.4vw, 84px);
  line-height: 1.02;
}
.banner-sub {
  max-width: 460px;
  margin: 0 0 30px;
  color: rgba(250, 248, 244, 0.82);
  font-size: 16px;
  line-height: 1.7;
}
.banner-btn {
  background: var(--paper);
  color: var(--ink);
  border-color: var(--paper);
}
.banner-btn:hover {
  background: var(--gold);
  border-color: var(--gold);
}
.banner-arrow {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 44px;
  height: 44px;
  border-radius: 50%;
  border: 1px solid rgba(250, 248, 244, 0.4);
  background: rgba(27, 22, 18, 0.35);
  color: var(--paper);
  font-size: 22px;
  cursor: pointer;
  backdrop-filter: blur(6px);
}
.banner-arrow.prev { left: 18px; }
.banner-arrow.next { right: 18px; }
.banner-dots {
  position: absolute;
  right: clamp(24px, 4vw, 56px);
  bottom: clamp(24px, 4vw, 56px);
  display: flex;
  gap: 8px;
}
.banner-dots button {
  width: 9px;
  height: 9px;
  border-radius: 999px;
  border: none;
  padding: 0;
  background: rgba(250, 248, 244, 0.45);
  cursor: pointer;
  transition: width 0.3s var(--ease-out), background 0.3s;
}
.banner-dots button.on {
  width: 28px;
  background: var(--paper);
}
@media (max-width: 640px) {
  .banners {
    border-radius: 20px;
  }
  .banner-arrow {
    display: none;
  }
  .banner-dots {
    left: 50%;
    right: auto;
    bottom: 16px;
    transform: translateX(-50%);
  }
  .banner-copy {
    padding-bottom: 48px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .banner-track {
    transition: none;
  }
}
</style>
