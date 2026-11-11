<template>
  <!--
    How it works: a scroll-driven walkthrough. On wide screens the section
    pins and scrolling plays each step's little scene (see build()); on phones
    each step plays as it comes into view; with reduced motion every scene
    shows its finished state. The scenes are illustrations only (aria-hidden):
    the step text says everything they show.
  -->
  <section id="how-it-works" ref="rootEl" class="hiw" aria-labelledby="hiw-heading">
    <div class="hiw-pin">
      <div class="bc-container hiw-inner">
        <header class="hiw-head">
          <p class="bc-label">How it works</p>
          <h2 id="hiw-heading">From a local florist's bench <em>to your door</em></h2>
          <p class="hiw-lede">Five steps, start to finish. <span class="hiw-lede-live">Keep scrolling to watch a bouquet come together.</span></p>
        </header>

        <nav class="hiw-rail" aria-label="How it works steps">
          <span class="hiw-rail-line" aria-hidden="true"><span ref="railFill" class="hiw-rail-fill"></span></span>
          <button
            v-for="(step, i) in STEPS"
            :key="step.key"
            type="button"
            class="hiw-rail-btn"
            :class="{ on: active === i, done: active > i }"
            :aria-current="active === i ? 'step' : undefined"
            @click="jumpTo(i)"
          >
            <span class="hiw-rail-num">{{ i + 1 }}</span>
            <span class="hiw-rail-label">{{ step.short }}</span>
          </button>
        </nav>

        <div class="hiw-steps">
          <article v-for="(step, i) in STEPS" :key="step.key" class="hiw-step" :data-step="i">
            <div class="hiw-copy">
              <p class="hiw-kicker">Step {{ i + 1 }} of {{ STEPS.length }}</p>
              <h3>{{ step.title }}</h3>
              <p class="hiw-text">{{ step.text }}</p>
              <p class="hiw-tip">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.8 4.6 4.7 1.9-4.7 1.9L12 16l-1.8-4.6-4.7-1.9 4.7-1.9z" /></svg>
                {{ step.tip }}
              </p>
            </div>

            <div class="hiw-scene" aria-hidden="true">
              <div class="hiw-window">
                <div class="hiw-bar">
                  <span></span><span></span><span></span>
                  <em>{{ step.screen }}</em>
                </div>

                <!-- 1. Find a florist: pins drop on a map, one shop opens -->
                <div v-if="step.key === 'find'" class="sc sc-map">
                  <svg class="sc-map-art" viewBox="0 0 400 280" preserveAspectRatio="xMidYMid slice">
                    <path class="sc-park" d="M250 30c40-10 90 10 100 50s-30 60-70 55-60-30-55-60 5-40 25-45z" />
                    <path class="sc-river" d="M-10 220c60-20 90 10 150-5s90-60 150-50 80 30 120 20" />
                    <path class="sc-road" d="M0 120h400M150 0v280M300 0v280M0 60l150 60M150 200h250" />
                  </svg>
                  <span class="sc-pin sc-pin-a" style="left: 20%; top: 26%"></span>
                  <span class="sc-pin sc-pin-b" style="left: 76%; top: 20%"></span>
                  <span class="sc-pin sc-pin-c" style="left: 66%; top: 62%"></span>
                  <span class="sc-pin sc-pin-hero" style="left: 44%; top: 40%"><i class="sc-pulse"></i></span>
                  <span class="sc-you" style="left: 30%; top: 70%"><i></i>You</span>
                  <div class="sc-shop">
                    <span class="sc-shop-logo">P</span>
                    <span class="sc-shop-copy">
                      <strong>Petal &amp; Co</strong>
                      <small>★ 4.9 · 1.2 km away</small>
                    </span>
                    <span class="sc-chip">3D designer ready</span>
                  </div>
                </div>

                <!-- 2. Occasion and size: a pointer picks both -->
                <div v-else-if="step.key === 'occasion'" class="sc sc-pick">
                  <p class="sc-q">What's the bouquet for?</p>
                  <div class="sc-occasions">
                    <span class="sc-occ">
                      <svg viewBox="0 0 24 24"><path d="M5 20h14v-7H5zM5 13c2 1.5 4 1.5 7 0s5-1.5 7 0M12 9V6M12 4.5v.01" /></svg>
                      Birthday
                    </span>
                    <span class="sc-occ sc-occ-pick">
                      <svg viewBox="0 0 24 24"><path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z" /></svg>
                      Anniversary
                      <i class="sc-ring"></i><b class="sc-check">✓</b>
                    </span>
                    <span class="sc-occ">
                      <svg viewBox="0 0 24 24"><path d="M2 9l10-5 10 5-10 5zM6 11v5c3 2 9 2 12 0v-5" /></svg>
                      Graduation
                    </span>
                  </div>
                  <p class="sc-q">How many flowers?</p>
                  <div class="sc-sizes">
                    <span class="sc-size"><b>3</b>small</span>
                    <span class="sc-size sc-size-pick"><b>6</b>classic<i class="sc-ring"></i></span>
                    <span class="sc-size"><b>12</b>grand</span>
                  </div>
                  <span class="sc-cursor"><svg viewBox="0 0 24 24"><path d="M6 3l12 7.5-5.2 1.3L10 17.5 6 3z" /></svg></span>
                </div>

                <!-- 3. Design in 3D: fillers first, then flowers, then the ribbon -->
                <div v-else-if="step.key === 'design'" class="sc sc-design">
                  <div class="sc-palette">
                    <span class="sc-swatch leaf"></span>
                    <span class="sc-swatch leaf alt"></span>
                    <span class="sc-swatch bloom"></span>
                    <span class="sc-swatch bloom alt"></span>
                  </div>
                  <svg class="sc-bouquet" viewBox="0 0 220 260">
                    <g class="sc-leaves">
                      <path class="sc-leaf" d="M58 98c-22-10-34-30-30-50 18 6 30 24 30 50z" />
                      <path class="sc-leaf" d="M162 98c22-10 34-30 30-50-18 6-30 24-30 50z" />
                      <path class="sc-leaf alt" d="M82 70c-12-18-10-40 4-52 8 16 8 36-4 52z" />
                      <path class="sc-leaf alt" d="M138 70c12-18 10-40-4-52-8 16-8 36 4 52z" />
                      <path class="sc-leaf" d="M110 58c-8-20-4-40 0-50 4 10 8 30 0 50z" />
                    </g>
                    <g class="sc-flowers">
                      <g v-for="(spot, f) in FLOWER_SPOTS" :key="f" class="sc-flower" :transform="`translate(${spot.x} ${spot.y})`">
                        <circle v-for="p in 5" :key="p" :cx="Math.cos((p * 72 * Math.PI) / 180) * 9" :cy="Math.sin((p * 72 * Math.PI) / 180) * 9" r="9.5" :fill="spot.color" />
                        <circle r="5.5" class="sc-flower-mid" />
                      </g>
                    </g>
                    <path class="sc-wrap-back" d="M34 104 L110 246 L186 104 Z" />
                    <path class="sc-wrap" d="M34 104 Q110 128 186 104 L118 246 L102 246 Z" />
                    <g class="sc-bow" transform="translate(110 188)">
                      <path d="M0 0L-24-12-22 12z M0 0L24-12 22 12z" />
                      <path d="M-3 3L-10 30M3 3L10 30" class="sc-bow-tail" />
                      <circle r="5" />
                    </g>
                  </svg>
                  <span class="sc-dragged"><i></i></span>
                  <div class="sc-counter">Flowers <b class="sc-count">6</b>/6</div>
                  <span class="sc-turn">↻ Drag to turn it around</span>
                </div>

                <!-- 4. Check out: the receipt adds up, a date, then pay -->
                <div v-else-if="step.key === 'checkout'" class="sc sc-checkout">
                  <div class="sc-receipt">
                    <p class="sc-r-title">Your bouquet</p>
                    <p class="sc-line"><span>6 stems</span><span>₱900</span></p>
                    <p class="sc-line"><span>Wrap, ribbon, fillers</span><span>Included</span></p>
                    <p class="sc-line"><span>Delivery</span><span>₱120</span></p>
                    <p class="sc-total"><span>Total</span><b class="sc-total-num">₱1,020</b></p>
                  </div>
                  <div class="sc-cal">
                    <p>November</p>
                    <div class="sc-days">
                      <span v-for="d in 14" :key="d" class="sc-day" :class="{ 'sc-day-pick': d === 12 }">{{ d + 9 }}<i v-if="d === 12" class="sc-ring"></i></span>
                    </div>
                  </div>
                  <div class="sc-pay">
                    <span class="sc-pay-idle">Place order</span>
                    <span class="sc-pay-done">✓ Order placed</span>
                  </div>
                  <span class="sc-cursor"><svg viewBox="0 0 24 24"><path d="M6 3l12 7.5-5.2 1.3L10 17.5 6 3z" /></svg></span>
                </div>

                <!-- 5. Delivered: the route draws, the van drives it, a photo arrives -->
                <div v-else class="sc sc-deliver">
                  <svg class="sc-route-art" viewBox="0 0 400 280" preserveAspectRatio="xMidYMid meet">
                    <path class="sc-road" d="M0 210h400M60 0v280M0 90h400M260 0v280" />
                    <path class="sc-route-base" :d="ROUTE" />
                    <path class="sc-route" :d="ROUTE" />
                    <g transform="translate(60 210)" class="sc-place">
                      <circle r="18" />
                      <path d="M-8 6V-2l8-6 8 6v8z" />
                    </g>
                    <g transform="translate(340 70)" class="sc-place home">
                      <circle r="18" />
                      <path d="M-9 8V-1l9-7 9 7v9h-6v-6h-6v6z" />
                    </g>
                    <g class="sc-van" transform="translate(340 70)">
                      <rect x="-14" y="-9" width="22" height="14" rx="3" />
                      <path d="M8 -5h5l4 5v5H8z" />
                      <circle cx="-7" cy="6" r="3.2" class="sc-wheel" />
                      <circle cx="10" cy="6" r="3.2" class="sc-wheel" />
                    </g>
                  </svg>
                  <div class="sc-status">
                    <span class="sc-stage">Prepared</span>
                    <span class="sc-stage">On the way</span>
                    <span class="sc-stage">Delivered</span>
                  </div>
                  <div class="sc-photo">
                    <span class="sc-photo-img">
                      <svg viewBox="0 0 60 60"><path d="M18 26 L30 54 L42 26 Z" fill="#e9d9c4" /><circle cx="24" cy="22" r="7" fill="#e88aa5" /><circle cx="36" cy="22" r="7" fill="#f2a07b" /><circle cx="30" cy="15" r="7" fill="#f3c6d3" /></svg>
                    </span>
                    <span class="sc-photo-copy"><strong>✓ Delivered</strong><small>with a photo from your florist</small></span>
                  </div>
                </div>
              </div>
            </div>
          </article>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref } from "vue";
import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

gsap.registerPlugin(ScrollTrigger);

const STEPS = [
  {
    key: "find",
    short: "Find a florist",
    screen: "Flower shops near you",
    title: "Find a florist near you",
    text: "Browse local flower shops and pick one with the 3D designer ready. Their real flowers, fillers and wraps become your palette.",
    tip: "Shops show their rating, distance and what they have in stock.",
  },
  {
    key: "occasion",
    short: "Occasion & size",
    screen: "Start a bouquet",
    title: "Choose the occasion and size",
    text: "Tell us what the bouquet is for and how many flowers you want. Each occasion comes with its own wrap style.",
    tip: "You can change both later without losing your work.",
  },
  {
    key: "design",
    short: "Design in 3D",
    screen: "3D designer",
    title: "Design it in 3D",
    text: "Fillers go in first, then the flowers. Tap or drag them onto the bouquet, or let us suggest an arrangement, then add the wrap and ribbon.",
    tip: "A guide walks you through your first bouquet, step by step.",
  },
  {
    key: "checkout",
    short: "Check out",
    screen: "Checkout",
    title: "Pick a date and check out",
    text: "See the price add up as you build, choose the day you need it, and pay by e-wallet or cash on delivery.",
    tip: "Wraps, ribbons and fillers are included in the price.",
  },
  {
    key: "deliver",
    short: "Delivered",
    screen: "Your order",
    title: "Hand-tied and delivered",
    text: "Your florist builds the bouquet you designed and brings it to your door. You can follow every stage of the order.",
    tip: "You get a photo of the bouquet when it's delivered.",
  },
];

const FLOWER_SPOTS = [
  { x: 72, y: 88, color: "#e88aa5" },
  { x: 148, y: 88, color: "#f2a07b" },
  { x: 110, y: 74, color: "#f3c6d3" },
  { x: 90, y: 104, color: "#f2a07b" },
  { x: 130, y: 104, color: "#e88aa5" },
  { x: 110, y: 98, color: "#c9567a" },
];
const ROUTE = "M60 210 C130 210 120 140 190 140 S300 80 340 70";

const rootEl = ref(null);
const railFill = ref(null);
const active = ref(0);
let trigger = null; // the pinned walkthrough's ScrollTrigger (wide screens)
let scrollToY = null;

// ── Scenes ─────────────────────────────────────────────────────────────
// Each returns a paused timeline that plays its step from start to finish.
// from() tweens render their start state right away, so a scene that hasn't
// played yet shows its empty start; a finished one shows the end state, which
// is also how the page looks with no animation at all.

const CURSOR_OFF = { autoAlpha: 0, x: 0, y: 0 };

function findScene(q) {
  const tl = gsap.timeline({ paused: true });
  tl.from(q(".sc-map-art"), { autoAlpha: 0, scale: 1.08, duration: 0.6, ease: "power2.out" })
    .from(q(".sc-pin"), { autoAlpha: 0, y: -26, duration: 0.45, stagger: 0.14, ease: "back.out(2)" }, 0.3)
    .from(q(".sc-you"), { autoAlpha: 0, scale: 0.5, duration: 0.35 }, 0.5)
    .to(q(".sc-pin-hero"), { scale: 1.3, duration: 0.3, ease: "power2.out" }, 1.2)
    .from(q(".sc-pulse"), { autoAlpha: 0, scale: 0.3, duration: 0.5 }, 1.2)
    .from(q(".sc-shop"), { autoAlpha: 0, y: 40, duration: 0.55, ease: "power3.out" }, 1.45)
    .from(q(".sc-chip"), { autoAlpha: 0, scale: 0.7, duration: 0.3, ease: "back.out(2)" }, 1.9);
  return tl;
}

function occasionScene(q) {
  const tl = gsap.timeline({ paused: true });
  const cursor = q(".sc-cursor")[0];
  const at = (selector) => {
    const box = q(selector)[0]?.getBoundingClientRect();
    const frame = cursor?.parentElement?.getBoundingClientRect();
    if (!box || !frame) return { x: 0, y: 0 };
    return { x: box.left - frame.left + box.width * 0.6, y: box.top - frame.top + box.height * 0.55 };
  };
  tl.from(q(".sc-occ"), { autoAlpha: 0, y: 18, duration: 0.4, stagger: 0.1, ease: "power2.out" })
    .from(q(".sc-size"), { autoAlpha: 0, y: 18, duration: 0.4, stagger: 0.1, ease: "power2.out" }, 0.2)
    .fromTo(cursor, { ...CURSOR_OFF, x: () => at(".sc-occ").x, y: () => at(".sc-occ").y + 60 }, { autoAlpha: 1, duration: 0.3 }, 0.6)
    .to(cursor, { x: () => at(".sc-occ-pick").x, y: () => at(".sc-occ-pick").y, duration: 0.6, ease: "power2.inOut" }, 0.8)
    .to(cursor, { scale: 0.85, duration: 0.1, yoyo: true, repeat: 1 }, 1.4)
    .from(q(".sc-occ-pick .sc-ring"), { autoAlpha: 0, scale: 0.9, duration: 0.3 }, 1.45)
    .from(q(".sc-occ-pick .sc-check"), { autoAlpha: 0, scale: 0, duration: 0.3, ease: "back.out(3)" }, 1.5)
    .to(cursor, { x: () => at(".sc-size-pick").x, y: () => at(".sc-size-pick").y, duration: 0.6, ease: "power2.inOut" }, 1.8)
    .to(cursor, { scale: 0.85, duration: 0.1, yoyo: true, repeat: 1 }, 2.4)
    .from(q(".sc-size-pick .sc-ring"), { autoAlpha: 0, scale: 0.9, duration: 0.3 }, 2.45)
    .to(cursor, { autoAlpha: 0, duration: 0.3 }, 2.9);
  return tl;
}

function designScene(q) {
  const tl = gsap.timeline({ paused: true });
  const count = q(".sc-count")[0];
  const counter = { n: 0 };
  if (count) count.textContent = "0";
  tl.from(q(".sc-wrap-back, .sc-wrap"), { autoAlpha: 0, y: 20, duration: 0.5, ease: "power2.out" })
    .from(q(".sc-palette .sc-swatch"), { autoAlpha: 0, x: -14, duration: 0.3, stagger: 0.08 }, 0.1)
    .from(q(".sc-leaf"), { scale: 0, transformOrigin: "50% 100%", duration: 0.45, stagger: 0.1, ease: "back.out(1.8)" }, 0.5)
    .from(q(".sc-flower"), { scale: 0, transformOrigin: "50% 50%", duration: 0.4, stagger: 0.18, ease: "back.out(2.2)" }, 1.2)
    .to(counter, { n: 6, duration: 0.18 * 5 + 0.4, ease: "none", onUpdate: () => count && (count.textContent = String(Math.round(counter.n))) }, 1.2)
    .fromTo(q(".sc-dragged"), { autoAlpha: 0, x: 0, y: 0 }, { autoAlpha: 1, duration: 0.2 }, 1.1)
    .to(q(".sc-dragged"), { x: () => dragGap(q).x, y: () => dragGap(q).y, duration: 0.7, ease: "power2.inOut" }, 1.3)
    .to(q(".sc-dragged"), { autoAlpha: 0, scale: 0.6, duration: 0.2 }, 2)
    .from(q(".sc-bow"), { scale: 0, transformOrigin: "50% 50%", duration: 0.45, ease: "back.out(2.4)" }, 2.5)
    .from(q(".sc-counter"), { autoAlpha: 0, y: 10, duration: 0.3 }, 0.9)
    .to(q(".sc-bouquet"), { rotation: -5, duration: 0.4, ease: "sine.inOut" }, 2.9)
    .to(q(".sc-bouquet"), { rotation: 4, duration: 0.6, ease: "sine.inOut" }, 3.3)
    .to(q(".sc-bouquet"), { rotation: 0, duration: 0.4, ease: "sine.inOut" }, 3.9)
    .from(q(".sc-turn"), { autoAlpha: 0, duration: 0.3 }, 2.9);
  return tl;
}

/** From the palette's flower swatch to the middle of the bouquet */
function dragGap(q) {
  const from = q(".sc-dragged")[0]?.getBoundingClientRect();
  const to = q(".sc-flower")[5]?.getBoundingClientRect();
  if (!from || !to) return { x: 120, y: 0 };
  return { x: to.left + to.width / 2 - (from.left + from.width / 2), y: to.top + to.height / 2 - (from.top + from.height / 2) };
}

function checkoutScene(q) {
  const tl = gsap.timeline({ paused: true });
  const total = q(".sc-total-num")[0];
  const money = { n: 0 };
  const peso = (value) => `₱${Math.round(value).toLocaleString("en-PH")}`;
  if (total) total.textContent = peso(0);
  const cursor = q(".sc-cursor")[0];
  const at = (selector) => {
    const box = q(selector)[0]?.getBoundingClientRect();
    const frame = cursor?.parentElement?.getBoundingClientRect();
    if (!box || !frame) return { x: 0, y: 0 };
    return { x: box.left - frame.left + box.width * 0.55, y: box.top - frame.top + box.height * 0.5 };
  };
  tl.from(q(".sc-receipt"), { autoAlpha: 0, y: 24, duration: 0.45, ease: "power2.out" })
    .from(q(".sc-line"), { autoAlpha: 0, x: -12, duration: 0.3, stagger: 0.2 }, 0.3)
    .to(money, { n: 1020, duration: 0.9, ease: "power1.out", onUpdate: () => total && (total.textContent = peso(money.n)) }, 0.4)
    .from(q(".sc-cal"), { autoAlpha: 0, y: 24, duration: 0.45, ease: "power2.out" }, 0.8)
    .from(q(".sc-pay"), { autoAlpha: 0, y: 16, duration: 0.4 }, 1)
    .fromTo(cursor, { ...CURSOR_OFF, x: () => at(".sc-cal").x, y: () => at(".sc-cal").y + 70 }, { autoAlpha: 1, duration: 0.25 }, 1.3)
    .to(cursor, { x: () => at(".sc-day-pick").x, y: () => at(".sc-day-pick").y, duration: 0.55, ease: "power2.inOut" }, 1.45)
    .from(q(".sc-day-pick .sc-ring"), { autoAlpha: 0, scale: 0.6, duration: 0.25, ease: "back.out(2)" }, 2.05)
    .to(cursor, { x: () => at(".sc-pay").x, y: () => at(".sc-pay").y, duration: 0.55, ease: "power2.inOut" }, 2.3)
    .to(cursor, { scale: 0.85, duration: 0.1, yoyo: true, repeat: 1 }, 2.9)
    .from(q(".sc-pay-done"), { autoAlpha: 0, y: 10, duration: 0.3 }, 3)
    .fromTo(q(".sc-pay-idle"), { autoAlpha: 1, y: 0 }, { autoAlpha: 0, y: -10, duration: 0.3 }, 3)
    .from(q(".sc-pay"), { backgroundColor: "#363f4e", duration: 0.3 }, 3)
    .to(cursor, { autoAlpha: 0, duration: 0.3 }, 3.3);
  return tl;
}

function deliverScene(q) {
  const tl = gsap.timeline({ paused: true });
  const route = q(".sc-route")[0];
  const van = q(".sc-van")[0];
  const length = route?.getTotalLength?.() || 400;
  const trip = { t: 1 };
  const drive = () => {
    if (!route || !van) return;
    const point = route.getPointAtLength(length * trip.t);
    route.style.strokeDashoffset = String(length * (1 - trip.t));
    gsap.set(van, { x: point.x, y: point.y - 8 });
  };
  if (route) route.style.strokeDasharray = `${length} ${length}`;
  trip.t = 0;
  drive();
  tl.from(q(".sc-route-art .sc-road, .sc-route-base"), { autoAlpha: 0, duration: 0.4 })
    .from(q(".sc-place"), { scale: 0, transformOrigin: "50% 50%", duration: 0.4, stagger: 0.2, ease: "back.out(2)" }, 0.2)
    .from(q(".sc-stage"), { autoAlpha: 0.35, duration: 0.01 }, 0)
    .to(q(".sc-stage")[0], { autoAlpha: 1, duration: 0.2 }, 0.6)
    .from(q(".sc-van"), { autoAlpha: 0, duration: 0.2 }, 0.7)
    .to(trip, { t: 1, duration: 1.6, ease: "power1.inOut", onUpdate: drive }, 0.8)
    .to(q(".sc-stage")[1], { autoAlpha: 1, duration: 0.2 }, 1)
    .to(q(".sc-stage")[2], { autoAlpha: 1, duration: 0.2 }, 2.4)
    .from(q(".sc-photo"), { autoAlpha: 0, y: 30, scale: 0.95, duration: 0.5, ease: "back.out(1.6)" }, 2.5);
  return tl;
}

const SCENES = { find: findScene, occasion: occasionScene, design: designScene, checkout: checkoutScene, deliver: deliverScene };

/**
 * Sets the walkthrough up; call it inside the page's gsap.context so a rebuild
 * (resize) reverts it. mode: "live" (pinned, scrubbed by scroll), "stack"
 * (each step plays as it scrolls in) or "static" (finished scenes, no motion).
 */
function build({ mode = "live", scrollTo = null } = {}) {
  const root = rootEl.value;
  if (!root) return;
  scrollToY = scrollTo;
  trigger = null;
  active.value = 0;
  root.classList.remove("hiw--live", "hiw--stack", "hiw--static");
  root.classList.add(`hiw--${mode}`);
  if (mode === "static") return;

  const steps = gsap.utils.toArray(root.querySelectorAll(".hiw-step"));
  const scenes = steps.map((step, i) => SCENES[STEPS[i].key](gsap.utils.selector(step)));

  if (mode === "stack") {
    steps.forEach((step, i) => {
      ScrollTrigger.create({
        trigger: step,
        start: "top 72%",
        once: true,
        onEnter: () => scenes[i].play(),
      });
    });
    return;
  }

  // Live: the section pins; each step gets one stretch of the scroll
  const master = gsap.timeline({
    defaults: { ease: "none" },
    scrollTrigger: {
      trigger: root,
      start: "top top",
      end: () => `+=${STEPS.length * 85}%`,
      pin: true,
      scrub: 0.8,
      invalidateOnRefresh: true,
      onUpdate: (self) => {
        active.value = Math.min(STEPS.length - 1, Math.floor(self.progress * STEPS.length + 0.02));
        if (railFill.value) railFill.value.style.transform = `scaleX(${self.progress})`;
      },
    },
  });
  trigger = master.scrollTrigger;
  steps.forEach((step, i) => {
    if (i > 0) {
      master.to(steps[i - 1], { autoAlpha: 0, y: -24, duration: 0.18, ease: "power1.in" }, i - 0.12);
      master.fromTo(step, { autoAlpha: 0, y: 28 }, { autoAlpha: 1, y: 0, duration: 0.22, ease: "power2.out" }, i + 0.04);
    }
    scenes[i].duration(0.72);
    scenes[i].paused(false);
    master.add(scenes[i], i + 0.12);
  });
  master.set({}, {}, STEPS.length);
}

/** Rail buttons: go to that step's part of the scroll */
function jumpTo(index) {
  const root = rootEl.value;
  if (trigger) {
    const y = trigger.start + (trigger.end - trigger.start) * ((index + 0.55) / STEPS.length);
    if (scrollToY) scrollToY(y);
    else window.scrollTo({ top: y, behavior: "smooth" });
    return;
  }
  root?.querySelectorAll(".hiw-step")[index]?.scrollIntoView({ behavior: "smooth", block: "center" });
}

defineExpose({ build, el: rootEl });
</script>

<style scoped>
.hiw {
  position: relative;
  padding-block: var(--section-space);
}

.hiw-head {
  max-width: 720px;
}

.hiw-head h2 {
  margin-top: 14px;
  font-size: clamp(38px, 4.6vw, 64px);
  line-height: 1.04;
}

.hiw-lede {
  margin-top: 16px;
  color: var(--ink-soft);
  font-size: 16px;
  line-height: 1.7;
}

.hiw-lede-live {
  display: none;
}

/* ── Rail: the five steps, clickable; only while pinned ── */
.hiw-rail {
  position: relative;
  display: none;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  margin-top: 28px;
}

.hiw-rail-line {
  position: absolute;
  left: 10%;
  right: 10%;
  top: 21px;
  height: 2px;
  border-radius: 2px;
  background: var(--line);
  overflow: hidden;
}

.hiw-rail-fill {
  display: block;
  height: 100%;
  background: var(--leaf);
  transform: scaleX(0);
  transform-origin: left;
}

.hiw-rail-btn {
  position: relative;
  display: grid;
  justify-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 4px;
  border: 0;
  background: none;
  color: var(--ink-soft);
  font-family: var(--font-sans);
  font-size: 13px;
  cursor: pointer;
}

.hiw-rail-num {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 1.5px solid var(--line);
  border-radius: 50%;
  background: var(--paper);
  color: var(--ink-soft);
  font-family: var(--font-display);
  font-size: 20px;
  transition: background 0.3s ease, border-color 0.3s ease, color 0.3s ease, transform 0.3s ease;
}

.hiw-rail-btn:hover .hiw-rail-num {
  border-color: var(--leaf);
  color: var(--ink);
}

.hiw-rail-btn.done .hiw-rail-num {
  border-color: var(--leaf);
  background: rgba(77, 177, 115, 0.12);
  color: var(--leaf-deep);
}

.hiw-rail-btn.on {
  color: var(--ink);
  font-weight: 500;
}

.hiw-rail-btn.on .hiw-rail-num {
  border-color: var(--leaf);
  background: var(--leaf);
  color: #ffffff;
  transform: scale(1.08);
}

.hiw-rail-btn:focus-visible {
  outline: none;
}

.hiw-rail-btn:focus-visible .hiw-rail-num {
  outline: 2px solid var(--leaf);
  outline-offset: 3px;
}

/* ── Steps: copy beside its scene ── */
.hiw-steps {
  display: grid;
  gap: 72px;
  margin-top: 64px;
}

.hiw-step {
  display: grid;
  grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.15fr);
  align-items: center;
  gap: clamp(32px, 5vw, 80px);
}

.hiw-kicker {
  color: var(--leaf-deep);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.hiw-copy h3 {
  margin-top: 10px;
  font-size: clamp(30px, 3vw, 44px);
  line-height: 1.08;
}

.hiw-text {
  max-width: 440px;
  margin-top: 16px;
  color: var(--ink-soft);
  font-size: 16px;
  line-height: 1.75;
}

.hiw-tip {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  max-width: 440px;
  margin-top: 22px;
  padding: 12px 14px;
  border-radius: 12px;
  background: rgba(77, 177, 115, 0.08);
  color: var(--ink);
  font-size: 14px;
  line-height: 1.55;
}

.hiw-tip svg {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  margin-top: 1px;
  fill: none;
  stroke: var(--leaf-deep);
  stroke-width: 1.6;
  stroke-linejoin: round;
}

/* ── The little app window each scene plays in ── */
.hiw-window {
  position: relative;
  width: 100%;
  max-width: 560px;
  aspect-ratio: 10 / 7.4;
  margin-inline: auto;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 22px;
  background: var(--paper);
  box-shadow: 0 30px 70px rgba(54, 63, 78, 0.14);
}

.hiw-bar {
  position: relative;
  z-index: 3;
  display: flex;
  align-items: center;
  gap: 6px;
  height: 38px;
  padding: 0 14px;
  border-bottom: 1px solid var(--line);
  background: #ffffff;
}

.hiw-bar span {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: var(--ivory-deep);
}

.hiw-bar em {
  margin-left: 10px;
  color: var(--ink-soft);
  font-size: 12px;
  font-style: normal;
  font-weight: 500;
}

.sc {
  position: absolute;
  inset: 38px 0 0;
  font-family: var(--font-sans);
}

.sc-ring {
  position: absolute;
  inset: -3px;
  border: 2px solid var(--leaf);
  border-radius: inherit;
  pointer-events: none;
}

.sc-cursor {
  position: absolute;
  left: 0;
  top: 0;
  z-index: 5;
  width: 26px;
  height: 26px;
  visibility: hidden;
  opacity: 0;
}

.sc-cursor svg {
  width: 100%;
  height: 100%;
  fill: #ffffff;
  stroke: var(--ink);
  stroke-width: 1.5;
  filter: drop-shadow(0 2px 3px rgba(38, 45, 57, 0.3));
}

/* 1. Map */
.sc-map {
  background: #eef3ee;
}

.sc-map-art {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
}

.sc-park {
  fill: #d6e8d6;
}

.sc-river {
  fill: none;
  stroke: #cfe0ec;
  stroke-width: 16;
  stroke-linecap: round;
}

.sc-road {
  fill: none;
  stroke: #ffffff;
  stroke-width: 9;
  stroke-linecap: round;
}

.sc-pin {
  position: absolute;
  width: 22px;
  height: 22px;
  margin: -22px 0 0 -11px;
  border-radius: 50% 50% 50% 0;
  background: #9aa4b4;
  transform: rotate(-45deg);
  box-shadow: 0 4px 8px rgba(38, 45, 57, 0.2);
}

.sc-pin::after {
  content: "";
  position: absolute;
  inset: 6px;
  border-radius: 50%;
  background: #ffffff;
}

.sc-pin-hero {
  z-index: 2;
  background: var(--leaf);
}

.sc-pulse {
  position: absolute;
  inset: -10px;
  border: 2px solid var(--leaf);
  border-radius: 50%;
  animation: sc-pulse 1.6s ease-out infinite;
}

@keyframes sc-pulse {
  from { transform: scale(0.6); opacity: 1; }
  to { transform: scale(1.6); opacity: 0; }
}

.sc-you {
  position: absolute;
  display: flex;
  align-items: center;
  gap: 6px;
  color: #3a6ea5;
  font-size: 11px;
  font-weight: 600;
}

.sc-you i {
  width: 12px;
  height: 12px;
  border: 2.5px solid #ffffff;
  border-radius: 50%;
  background: #3a6ea5;
  box-shadow: 0 0 0 5px rgba(58, 110, 165, 0.2);
}

.sc-shop {
  position: absolute;
  left: 6%;
  right: 6%;
  bottom: 6%;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px 12px;
  padding: 12px 14px;
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 14px 30px rgba(38, 45, 57, 0.16);
}

.sc-shop-logo {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 12px;
  background: var(--ink);
  color: var(--paper);
  font-family: var(--font-display);
  font-size: 20px;
}

.sc-shop-copy {
  display: grid;
  flex: 1;
  gap: 1px;
  min-width: 120px;
}

.sc-shop-copy strong {
  font-size: 14px;
  font-weight: 600;
}

.sc-shop-copy small {
  color: var(--ink-soft);
  font-size: 12px;
}

.sc-chip {
  padding: 5px 10px;
  border-radius: 999px;
  background: rgba(77, 177, 115, 0.14);
  color: var(--leaf-deep);
  font-size: 11.5px;
  font-weight: 600;
}

/* 2. Occasion and size */
.sc-pick {
  display: grid;
  align-content: center;
  gap: 10px;
  padding: 0 7%;
}

.sc-q {
  color: var(--ink);
  font-family: var(--font-display);
  font-size: clamp(17px, 2vw, 22px);
}

.sc-occasions,
.sc-sizes {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 8px;
}

.sc-occ,
.sc-size {
  position: relative;
  display: grid;
  justify-items: center;
  gap: 6px;
  padding: 12px 6px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #ffffff;
  color: var(--ink);
  font-size: 12.5px;
  font-weight: 500;
}

.sc-occ svg {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: var(--leaf-deep);
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sc-check {
  position: absolute;
  top: -8px;
  right: -8px;
  display: grid;
  place-items: center;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--leaf);
  color: #ffffff;
  font-size: 12px;
}

.sc-size {
  color: var(--ink-soft);
  font-size: 11px;
}

.sc-size b {
  color: var(--ink);
  font-family: var(--font-display);
  font-size: 24px;
  font-weight: 500;
  line-height: 1;
}

/* 3. Design */
.sc-design {
  background: radial-gradient(110% 90% at 60% 40%, #fafbfa 0%, #e8eee9 70%);
}

.sc-palette {
  position: absolute;
  left: 5%;
  top: 12%;
  display: grid;
  gap: 8px;
  padding: 8px;
  border: 1px solid var(--line);
  border-radius: 14px;
  background: #ffffff;
}

.sc-swatch {
  width: 30px;
  height: 30px;
  border-radius: 9px;
}

.sc-swatch.leaf { background: #7fb07f; }
.sc-swatch.leaf.alt { background: #a8c79a; }
.sc-swatch.bloom { background: #e88aa5; }
.sc-swatch.bloom.alt { background: #f2a07b; }

.sc-bouquet {
  position: absolute;
  left: 28%;
  top: 4%;
  width: 52%;
  height: 92%;
  overflow: visible;
}

.sc-leaf { fill: #7fb07f; }
.sc-leaf.alt { fill: #a8c79a; }
.sc-flower-mid { fill: #f3d48a; }
.sc-wrap-back { fill: #d9c4a8; }
.sc-wrap { fill: #ead9c2; }
.sc-bow { fill: #c9567a; }
.sc-bow-tail { fill: none; stroke: #c9567a; stroke-width: 4; stroke-linecap: round; }

.sc-dragged {
  position: absolute;
  left: calc(5% + 8px);
  top: calc(12% + 84px);
  z-index: 4;
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  border-radius: 50%;
  background: #c9567a;
  box-shadow: 0 8px 18px rgba(38, 45, 57, 0.28);
  visibility: hidden;
  opacity: 0;
}

.sc-dragged i {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: #f3d48a;
}

.sc-counter {
  position: absolute;
  right: 5%;
  top: 12%;
  padding: 8px 12px;
  border-radius: 12px;
  background: #ffffff;
  color: var(--ink-soft);
  font-size: 12px;
  box-shadow: 0 6px 16px rgba(38, 45, 57, 0.08);
}

.sc-counter b {
  color: var(--leaf-deep);
  font-size: 15px;
}

.sc-turn {
  position: absolute;
  right: 5%;
  bottom: 7%;
  padding: 6px 10px;
  border-radius: 999px;
  background: rgba(54, 63, 78, 0.08);
  color: var(--ink-soft);
  font-size: 11.5px;
}

/* 4. Checkout */
.sc-checkout {
  display: grid;
  grid-template-columns: 1.1fr 0.9fr;
  grid-template-rows: 1fr auto;
  gap: 12px;
  padding: 16px 6%;
  background: var(--ivory, #f2f5f2);
}

.sc-receipt,
.sc-cal {
  padding: 14px;
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 8px 20px rgba(38, 45, 57, 0.06);
}

.sc-r-title,
.sc-cal p {
  margin-bottom: 8px;
  font-family: var(--font-display);
  font-size: 18px;
}

.sc-line,
.sc-total {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  padding: 5px 0;
  color: var(--ink-soft);
  font-size: 12px;
}

.sc-total {
  margin-top: 6px;
  padding-top: 10px;
  border-top: 1px dashed var(--line);
  color: var(--ink);
  font-size: 13px;
}

.sc-total b {
  font-variant-numeric: tabular-nums;
  font-size: 16px;
}

.sc-days {
  display: grid;
  grid-template-columns: repeat(7, minmax(0, 1fr));
  gap: 4px;
}

.sc-day {
  position: relative;
  display: grid;
  place-items: center;
  aspect-ratio: 1;
  border-radius: 8px;
  color: var(--ink-soft);
  font-size: 11px;
}

.sc-day-pick {
  background: var(--leaf);
  color: #ffffff;
  font-weight: 600;
}

.sc-pay {
  position: relative;
  grid-column: 1 / -1;
  display: grid;
  place-items: center;
  height: 46px;
  border-radius: 999px;
  background: var(--leaf-deep);
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
}

.sc-pay > span {
  grid-area: 1 / 1;
}

.sc-pay-idle {
  visibility: hidden;
  opacity: 0;
}

/* 5. Delivery */
.sc-deliver {
  background: #eef3ee;
}

.sc-route-art {
  position: absolute;
  inset: 0 0 auto;
  width: 100%;
  height: 74%;
}

.sc-route-base {
  fill: none;
  stroke: rgba(54, 63, 78, 0.12);
  stroke-width: 6;
  stroke-linecap: round;
  stroke-dasharray: 2 10;
}

.sc-route {
  fill: none;
  stroke: var(--leaf);
  stroke-width: 6;
  stroke-linecap: round;
}

.sc-place circle {
  fill: #ffffff;
  stroke: var(--ink);
  stroke-width: 2;
}

.sc-place path {
  fill: var(--ink);
}

.sc-place.home circle {
  stroke: var(--leaf);
}

.sc-place.home path {
  fill: var(--leaf-deep);
}

.sc-van rect,
.sc-van path {
  fill: var(--ink);
}

.sc-wheel {
  fill: #ffffff;
  stroke: var(--ink);
  stroke-width: 2;
}

.sc-status {
  position: absolute;
  left: 6%;
  right: 6%;
  bottom: 30%;
  display: flex;
  justify-content: space-between;
  gap: 6px;
}

.sc-stage {
  padding: 5px 10px;
  border-radius: 999px;
  background: #ffffff;
  color: var(--leaf-deep);
  font-size: 11.5px;
  font-weight: 600;
}

.sc-photo {
  position: absolute;
  left: 6%;
  right: 6%;
  bottom: 6%;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 16px;
  background: #ffffff;
  box-shadow: 0 14px 30px rgba(38, 45, 57, 0.14);
}

.sc-photo-img {
  display: grid;
  place-items: center;
  width: 48px;
  height: 48px;
  border-radius: 10px;
  background: #f3efe8;
}

.sc-photo-img svg {
  width: 40px;
  height: 40px;
}

.sc-photo-copy {
  display: grid;
}

.sc-photo-copy strong {
  color: var(--leaf-deep);
  font-size: 14px;
}

.sc-photo-copy small {
  color: var(--ink-soft);
  font-size: 12px;
}

/* ── Live: the section pins and one step shows at a time ── */
.hiw--live {
  padding-block: 0;
}

.hiw--live .hiw-pin {
  display: flex;
  align-items: center;
  min-height: 100vh;
  min-height: 100svh;
  padding-block: 88px 24px;
}

.hiw--live .hiw-head {
  max-width: none;
}

.hiw--live .hiw-inner {
  width: 100%;
}

.hiw--live .hiw-lede-live {
  display: inline;
}

.hiw--live .hiw-head h2 {
  font-size: clamp(30px, 3.1vw, 46px);
}

.hiw--live .hiw-lede {
  margin-top: 10px;
}

.hiw--live .hiw-rail {
  display: grid;
}

.hiw--live .hiw-steps {
  position: relative;
  display: block;
  height: min(430px, 46vh);
  margin-top: 28px;
}

.hiw--live .hiw-step {
  position: absolute;
  inset: 0;
}

.hiw--live .hiw-window {
  max-height: min(430px, 46vh);
  width: auto;
  height: 100%;
}

/* Phones and narrow windows: the scene goes under its text */
@media (max-width: 967px) {
  .hiw-step {
    grid-template-columns: 1fr;
    gap: 24px;
  }

  .hiw-steps {
    gap: 64px;
    margin-top: 44px;
  }

  .hiw-copy h3 {
    font-size: 30px;
  }

  .sc-q {
    font-size: 16px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .sc-pulse {
    animation: none;
  }
}
</style>
