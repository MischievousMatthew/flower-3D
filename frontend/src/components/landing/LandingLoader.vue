<template>
  <div
    ref="root"
    class="landing-loader"
    role="status"
    aria-live="polite"
    :aria-label="`Loading BloomCraft, ${displayed} percent`"
  >
    <div class="loader-inner">
      <img
        ref="logo"
        class="loader-logo"
        src="/bloomcraft-blankBg.png"
        alt=""
        width="64"
        height="64"
      />
      <p class="loader-wordmark" aria-hidden="true">
        <span v-for="(letter, i) in letters" :key="i" class="loader-letter">{{ letter }}</span>
      </p>
      <p class="loader-tagline" aria-hidden="true">Bespoke florals, built by you</p>
    </div>

    <div class="loader-meter" aria-hidden="true">
      <span class="loader-count">{{ displayed }}<small>%</small></span>
      <span class="loader-line"><span ref="line" class="loader-line-fill"></span></span>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import { gsap } from "gsap";

const props = defineProps({
  // Real load progress, 0..1 (driven by the bouquet GLTFLoader).
  progress: { type: Number, default: 0 },
  // True once the bouquet has loaded (or failed / timed out).
  done: { type: Boolean, default: false },
  reducedMotion: { type: Boolean, default: false },
});
const emit = defineEmits(["finished"]);

const letters = "BloomCraft".split("");
const root = ref(null);
const logo = ref(null);
const line = ref(null);
const displayed = ref(0);

const counter = { value: 0 };
let counterTween = null;
let ctx = null;
let exiting = false;

function animateCounterTo(target, duration = 0.6) {
  counterTween?.kill();
  counterTween = gsap.to(counter, {
    value: target,
    duration,
    ease: "power2.out",
    onUpdate: () => {
      displayed.value = Math.round(counter.value);
      if (line.value) line.value.style.transform = `scaleX(${counter.value / 100})`;
    },
    onComplete: () => {
      if (props.done && counter.value >= 100) exit();
    },
  });
}

function exit() {
  if (exiting || !root.value) return;
  exiting = true;

  if (props.reducedMotion) {
    gsap.to(root.value, { autoAlpha: 0, duration: 0.3, onComplete: () => emit("finished") });
    return;
  }

  ctx.add(() => {
    gsap
      .timeline({ onComplete: () => emit("finished") })
      .to(".loader-letter", { yPercent: -110, autoAlpha: 0, duration: 0.45, stagger: 0.025, ease: "power3.in" })
      .to([logo.value, ".loader-tagline", ".loader-meter"], { autoAlpha: 0, duration: 0.35 }, "<0.1")
      .to(root.value, { clipPath: "inset(0 0 100% 0)", duration: 0.9, ease: "expo.inOut" }, "-=0.1");
  });
}

watch(
  () => props.progress,
  (value) => animateCounterTo(Math.min(value, 0.99) * 100),
);

watch(
  () => props.done,
  (isDone) => {
    if (isDone) animateCounterTo(100, props.reducedMotion ? 0.2 : 0.5);
  },
);

onMounted(() => {
  ctx = gsap.context(() => {
    if (props.reducedMotion) return;
    gsap.from(".loader-letter", { yPercent: 110, autoAlpha: 0, duration: 0.8, stagger: 0.05, ease: "power3.out" });
    gsap.from(logo.value, { scale: 0.6, autoAlpha: 0, duration: 0.8, ease: "power3.out" });
    gsap.from(".loader-tagline", { autoAlpha: 0, y: 8, duration: 0.6, delay: 0.5 });
  }, root.value);
  if (props.done) animateCounterTo(100);
});

onBeforeUnmount(() => {
  counterTween?.kill();
  ctx?.revert();
});
</script>

<style scoped>
.landing-loader {
  position: fixed;
  inset: 0;
  z-index: 3000;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background:
    radial-gradient(circle at 50% 42%, rgba(184, 101, 88, 0.1), transparent 45%),
    #f4f1ec;
  color: #1e1b18;
  clip-path: inset(0 0 0 0);
}

.loader-inner {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
}

.loader-logo {
  width: 64px;
  height: 64px;
  object-fit: contain;
}

.loader-wordmark {
  display: flex;
  overflow: hidden;
  margin: 0;
  font-family: "Cormorant Garamond", Georgia, serif;
  font-size: clamp(38px, 7vw, 76px);
  font-weight: 500;
  letter-spacing: -0.03em;
  line-height: 1.1;
}

.loader-letter {
  display: inline-block;
}

.loader-letter:nth-child(n + 6) {
  color: #b86558;
}

.loader-tagline {
  margin: 0;
  font-family: "Poppins", sans-serif;
  font-size: 14px;
  font-style: italic;
  color: #6e6761;
}

.loader-meter {
  position: absolute;
  right: 6%;
  bottom: 7%;
  left: 6%;
  display: flex;
  align-items: flex-end;
  gap: 24px;
}

.loader-count {
  min-width: 3.2ch;
  font-family: "Cormorant Garamond", Georgia, serif;
  font-size: clamp(28px, 4vw, 44px);
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  line-height: 1;
}

.loader-count small {
  font-size: 0.45em;
  margin-left: 2px;
  color: #6e6761;
}

.loader-line {
  flex: 1;
  height: 1px;
  margin-bottom: 8px;
  background: rgba(30, 27, 24, 0.12);
  overflow: hidden;
}

.loader-line-fill {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, #b86558, #d4b27a);
  transform: scaleX(0);
  transform-origin: left;
}

@media (max-width: 640px) {
  .loader-meter {
    gap: 14px;
  }
}
</style>
