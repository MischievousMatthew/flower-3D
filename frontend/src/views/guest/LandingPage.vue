<template>
  <div class="landing-page">
    <LandingLoader
      v-if="showLoader"
      :progress="loaderProgress"
      :done="loaderDone"
      :reduced-motion="reducedMotionPref"
      @finished="onIntroFinished"
    />

    <a href="#main-content" class="skip-link" @click.prevent="skipToContent"
      >Skip to content</a
    >

    <!-- Page scroll progress -->
    <div class="scroll-progress" aria-hidden="true">
      <span ref="scrollProgressFill" class="scroll-progress-fill"></span>
    </div>

    <div class="bg-blob blob-rose" aria-hidden="true"></div>
    <div class="bg-blob blob-sage" aria-hidden="true"></div>
    <div class="grain-overlay" aria-hidden="true"></div>

    <!-- ============================================================
         PERSISTENT 3D FLOWER LAYER
         Fixed, full-viewport, travels the entire page via GSAP
         ScrollTrigger. Never unmounted, never disappears.
    ============================================================= -->
    <div ref="flowerStage" class="flower-stage-fixed" aria-hidden="true">
      <span class="drift-petal petal-a" aria-hidden="true"></span>
      <span class="drift-petal petal-b" aria-hidden="true"></span>
      <span class="drift-petal petal-c" aria-hidden="true"></span>
      <span class="drift-petal petal-d" aria-hidden="true"></span>
      <span class="drift-petal petal-e" aria-hidden="true"></span>
      <canvas ref="flowerCanvas" class="flower-canvas"></canvas>
    </div>

    <!-- Cinematic blackout: an espresso iris that closes the page into the footer -->
    <div ref="blackoutLayer" class="blackout" aria-hidden="true"></div>

    <!-- Navigation: hides while scrolling down, returns on scroll up -->
    <nav
      class="navbar"
      :class="{
        'navbar--scrolled': navScrolled,
        'navbar--hidden': navHidden,
        'navbar--menu-open': mobileMenuOpen,
      }"
      aria-label="Main navigation"
    >
      <router-link to="/" class="logo" @click="closeMobileMenu">
        <span>
          <img
            src="/bloomcraft-blankBg.png"
            alt="Bloomcraft Logo"
            width="50"
            height="50"
          />
        </span>
        <span class="logo-text">BloomCraft</span>
      </router-link>
      <div id="primary-nav" class="nav-links">
        <router-link to="/shop" @click="closeMobileMenu">Shop</router-link>
        <router-link to="/pricing" @click="closeMobileMenu"
          >Pricing</router-link
        >
        <a href="#features" @click.prevent="navigateTo('features')"
          >Features</a
        >
        <a href="#how-it-works" @click.prevent="navigateTo('how-it-works')"
          >How It Works</a
        >
        <a href="#plans" @click.prevent="navigateTo('plans')">For Vendors</a>
        <a href="#blog" @click.prevent="navigateTo('blog')">Blog</a>
        <a href="#about" @click.prevent="navigateTo('about')">About</a>
        <router-link
          v-if="!isAuthenticated"
          to="/guest/login"
          class="nav-menu-login"
          @click="closeMobileMenu"
          >Login</router-link
        >
      </div>
      <div class="nav-buttons">
        <template v-if="!isAuthenticated">
          <router-link to="/guest/login" class="btn-login">Login</router-link>
          <router-link to="/guest/register" class="btn-register"
            >Register</router-link
          >
        </template>
        <button
          type="button"
          class="nav-toggle"
          :aria-expanded="mobileMenuOpen"
          aria-controls="primary-nav"
          :aria-label="mobileMenuOpen ? 'Close menu' : 'Open menu'"
          @click="mobileMenuOpen = !mobileMenuOpen"
        >
          <span class="nav-toggle-bar" aria-hidden="true"></span>
          <span class="nav-toggle-bar" aria-hidden="true"></span>
        </button>
      </div>
    </nav>

    <!-- Scroll Panels Story -->
    <main id="main-content" class="scroll-story-container" tabindex="-1">
      <!-- Panel 1: Hero Section -->
      <section class="panel-section hero-panel" id="top" ref="heroSection">
        <div class="hero-halo" aria-hidden="true">
          <span class="halo-ring halo-ring--outer"></span>
          <span class="halo-ring halo-ring--inner"></span>
        </div>
        <div class="panel-container hero-container">
          <div class="panel-content text-left-side hero-content">
            <span class="hero-kicker hero-reveal"
              >Bespoke florals, built by you</span
            >
            <h1 class="hero-title" aria-label="Create your perfect bouquet">
              <span class="line" aria-hidden="true"
                ><span class="line-inner">Create your</span></span
              >
              <span class="line" aria-hidden="true"
                ><span class="line-inner"
                  >perfect <span class="highlight">bouquet</span></span
                ></span
              >
            </h1>
            <p class="hero-desc hero-reveal">
              Where vendors meet creativity. Design custom flower arrangements
              in 3D or let our AI suggest the perfect bloom for every occasion.
            </p>
            <div class="hero-cta-wrap hero-reveal">
              <router-link to="/guest/register" class="btn-register btn-hero">
                Get Started
              </router-link>
            </div>
          </div>
          <div class="panel-visual spacer-right" aria-hidden="true"></div>
        </div>
        <div class="hero-scroll-cue hero-reveal" aria-hidden="true">
          <span>Scroll to explore</span>
          <span class="cue-line"></span>
        </div>
      </section>

      <!-- Bouquet Story: pinned, scroll-driven tour of the real 3D bouquet.
           Absorbs the former 3D Designer, Features and AI Concierge panels. -->
      <section
        id="bouquet-story"
        ref="storySection"
        class="story"
        :class="{ 'story--static': storyStatic }"
        aria-labelledby="story-heading"
      >
        <div class="story-pin">
          <div class="story-inner">
            <header class="story-head">
              <span class="eyebrow">The 3D Bouquet Studio</span>
              <h2 id="story-heading" class="sr-only">
                Build a bouquet in three steps
              </h2>
              <ol class="story-index" aria-hidden="true">
                <li
                  v-for="(step, i) in storySteps"
                  :key="step.key"
                  :class="{ 'is-active': activeStep === i }"
                >
                  <span class="story-index-num">0{{ i + 1 }}</span>
                  <span class="story-index-label">{{ step.label }}</span>
                </li>
              </ol>
            </header>

            <div class="story-steps" @focusin="revealFocusedStep">
              <article id="features" class="story-step" data-step="0"
                :class="{ 'is-current': activeStep === 0 }"
              >
                <span class="story-step-num">01 — Blooms</span>
                <h3>Choose your blooms</h3>
                <p>
                  Pick real flowers from local vendors and arrange them in
                  real time. Rotate, adjust and see every petal before you
                  order.
                </p>
              </article>

              <article class="story-step" data-step="1"
                :class="{ 'is-current': activeStep === 1 }"
              >
                <span class="story-step-num">02 — Wrapper</span>
                <h3>Wrap it your way</h3>
                <p>
                  Switch paper and inner layers to match the occasion, from
                  kraft and linen to blush and plum.
                </p>
                <div class="swatch-group" role="group" aria-label="Paper colour">
                  <button
                    v-for="swatch in paperSwatches"
                    :key="swatch.color"
                    type="button"
                    class="swatch"
                    :class="{ 'is-active': paperColor === swatch.color }"
                    :style="{ '--swatch': swatch.color }"
                    :aria-label="`${swatch.name} paper`"
                    :aria-pressed="paperColor === swatch.color"
                    @click="pickPaper(swatch.color)"
                  ></button>
                </div>
                <div class="swatch-group" role="group" aria-label="Inner layer colour">
                  <button
                    v-for="swatch in innerSwatches"
                    :key="swatch.color"
                    type="button"
                    class="swatch swatch--small"
                    :class="{ 'is-active': innerColor === swatch.color }"
                    :style="{ '--swatch': swatch.color }"
                    :aria-label="`${swatch.name} inner layer`"
                    :aria-pressed="innerColor === swatch.color"
                    @click="pickInner(swatch.color)"
                  ></button>
                </div>
              </article>

              <article id="how-it-works" class="story-step" data-step="2"
                :class="{ 'is-current': activeStep === 2 }"
              >
                <span class="story-step-num">03 — Fillers</span>
                <h3>Finish with fillers</h3>
                <p>
                  Eucalyptus, baby's breath and greenery bring it together.
                  Not sure where to start? Our AI concierge suggests the
                  perfect combination for the occasion, season and recipient.
                </p>
              </article>

              <article class="story-step story-step--reveal" data-step="3"
                :class="{ 'is-current': activeStep === 3 }"
              >
                <span class="story-step-num">Your bouquet</span>
                <h3>Every arrangement is unique, just like your story.</h3>
                <ul class="story-features">
                  <li v-for="feature in features" :key="feature.id">
                    <span class="story-feature-icon" aria-hidden="true">{{
                      feature.icon
                    }}</span>
                    <span>{{ feature.title }}</span>
                  </li>
                </ul>
                <button
                  class="btn-learn-more"
                  @click="handleLearnMore('3d-designer')"
                >
                  Explore 3D Designer
                </button>
              </article>
            </div>
          </div>
          <div class="story-progress" aria-hidden="true">
            <span ref="storyProgressFill" class="story-progress-fill"></span>
          </div>
        </div>
      </section>

      <!-- Panel 6: Stats Section -->
      <section
        class="panel-section stats-panel"
        id="stats"
        ref="statsSection"
      >
        <div class="panel-container">
          <div class="panel-content">
            <h2 class="reveal-fade-up">The marketplace, right now</h2>
            <p class="reveal-fade-up stats-header-desc">
              A snapshot of what's moving through BloomCraft today.
            </p>
            <div class="stats-stacked-grid">
              <div
                v-for="stat in statsData"
                :key="stat.label"
                class="stat-card-item reveal-card"
              >
                <div class="stat-icon">{{ stat.icon }}</div>
                <div class="stat-number">
                  {{ stat.number }}
                  <span
                    v-if="stat.live"
                    class="live-dot"
                    aria-hidden="true"
                  ></span>
                </div>
                <div class="stat-label">{{ stat.label }}</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Plans: pinned horizontal track of subscription cards -->
      <section
        id="plans"
        ref="plansSection"
        class="plans-section"
        :class="{ 'plans-section--static': storyStatic }"
        aria-labelledby="plans-heading"
      >
        <div class="plans-inner">
          <div class="plans-head">
            <span class="eyebrow">For flower businesses</span>
            <h2 id="plans-heading">Plans that grow with your shop</h2>
            <p>
              Start with the essentials, then grow into a connected ERP
              workspace when your operations need more.
            </p>
            <router-link to="/pricing" class="plans-compare"
              >Compare all features <span aria-hidden="true">→</span></router-link
            >
            <div class="plans-progress" aria-hidden="true">
              <span ref="plansProgressFill" class="plans-progress-fill"></span>
            </div>
          </div>
          <div ref="plansViewport" class="plans-viewport">
            <div ref="plansTrack" class="plans-track" @focusin="revealFocusedPlan">
              <PricingPlanCard
                v-for="plan in subscriptionPlans"
                :key="plan.key"
                class="plans-card"
                variant="landing"
                :plan="plan"
                @select="handlePlanSelect"
              />
            </div>
          </div>
          <p v-if="planNotice" class="plans-notice" role="status">
            {{ planNotice }}
            <router-link to="/guest/vendor_register">Become a vendor</router-link>
          </p>
        </div>
      </section>

      <!-- Panel 7: Blog Section -->
      <section class="panel-section blog-panel" id="blog" ref="blogSection">
        <div class="panel-container">
          <div class="panel-content text-left-side">
            <h2 class="reveal-fade-up">The BloomCraft Journal</h2>
            <p class="reveal-fade-up blog-header-desc">
              Tips, trends, and stories from the world of flowers
            </p>
            <div class="blog-stacked-grid">
              <div
                v-for="post in blogPosts"
                :key="post.id"
                class="blog-card-item reveal-card"
              >
                <div class="blog-image">
                  Blog Image {{ post.id }}<br />400x250px
                </div>
                <div class="blog-content">
                  <h3>{{ post.title }}</h3>
                  <a
                    href="#"
                    @click.prevent="readBlog(post.id)"
                    class="blog-link"
                  >
                    Read more →
                  </a>
                </div>
              </div>
            </div>
          </div>
          <div class="panel-visual spacer-right" aria-hidden="true"></div>
        </div>
      </section>

      <!-- About Us: Vision and Mission -->
      <section
        id="about"
        ref="aboutSection"
        class="about-section"
        aria-labelledby="about-heading"
      >
        <div class="about-inner">
          <header class="about-head">
            <span class="eyebrow">About us</span>
            <h2 id="about-heading">The purpose behind every BloomCraft bouquet</h2>
          </header>
          <div class="about-grid">
            <article
              v-for="(statement, i) in aboutStatements"
              :key="statement.title"
              class="about-card"
            >
              <span class="about-num">0{{ i + 1 }}</span>
              <h3>{{ statement.title }}</h3>
              <p class="about-text">
                <span
                  v-for="(word, w) in statement.words"
                  :key="w"
                  class="about-word"
                  >{{ word }}{{ " " }}</span
                >
              </p>
            </article>
          </div>
          <div class="about-rule" aria-hidden="true"></div>
        </div>
      </section>

      <!-- Panel 8: CTA Section -->
      <section class="panel-section cta-panel" ref="ctaSection">
        <div class="panel-container cta-container">
          <div class="cta-content">
            <h2 class="reveal-fade-up">Ready to create something beautiful?</h2>
            <div class="reveal-fade-up">
              <router-link to="/guest/register" class="btn-cta">
                Start Designing Now
              </router-link>
            </div>
          </div>
        </div>
      </section>
    </main>

    <button
      type="button"
      class="back-to-top"
      :class="{ 'is-visible': showBackToTop }"
      aria-label="Back to top"
      :tabindex="showBackToTop ? 0 : -1"
      :aria-hidden="!showBackToTop"
      @click="scrollToTop"
    >
      <svg class="btt-ring" viewBox="0 0 52 52" aria-hidden="true">
        <circle class="btt-ring-track" cx="26" cy="26" r="24" />
        <circle
          ref="backToTopRing"
          class="btt-ring-fill"
          cx="26"
          cy="26"
          r="24"
        />
      </svg>
      <svg class="btt-arrow" viewBox="0 0 24 24" aria-hidden="true">
        <path d="M12 19V5M5 12l7-7 7 7" />
      </svg>
    </button>

    <PlanAccountModal
      :plan="accountModalPlan"
      @close="accountModalPlan = null"
      @login="continueWithLogin"
      @register="continueWithRegister"
    />

    <!-- Footer -->
    <footer class="footer" ref="footerSection">
      <div class="footer-content">
        <div class="footer-brand">
          <div class="logo">
            <span>
              <img
                src="/bloomcraft-darkmode-removebg.png"
                alt="Bloomcraft Logo"
                width="60"
                height="60"
              />
            </span>
            <span>BloomCraft</span>
          </div>
          <p>
            Your marketplace for custom flower arrangements. Connect with local
            vendors and design the perfect bouquet.
          </p>
          <div class="social-links">
            <a
              v-for="social in socialLinks"
              :key="social.name"
              :href="social.url"
              class="social-link"
              target="_blank"
            >
              {{ social.icon }}
            </a>
          </div>
        </div>
        <div class="footer-section">
          <h4>Company</h4>
          <ul>
            <li v-for="link in companyLinks" :key="link.name">
              <a :href="link.url" @click.prevent="handleFooterLink(link.url)">
                {{ link.name }}
              </a>
            </li>
          </ul>
        </div>
        <div class="footer-section">
          <h4>Support</h4>
          <ul>
            <li v-for="link in supportLinks" :key="link.name">
              <a :href="link.url" @click.prevent="handleFooterLink(link.url)">
                {{ link.name }}
              </a>
            </li>
          </ul>
        </div>
        <div class="footer-section">
          <h4>Get Started</h4>
          <ul>
            <li><router-link to="/guest/register">Sign Up</router-link></li>
            <li><router-link to="/guest/login">Login</router-link></li>
            <li id="register-vendor">
              <router-link to="/guest/vendor_register"
                >Become a Vendor</router-link
              >
            </li>
            <li>
              <router-link to="/pricing">Pricing</router-link>
            </li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>Copyright © {{ currentYear }} BloomCraft. All rights reserved.</p>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from "vue";
import { useRouter } from "vue-router";
import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import Lenis from "lenis";
import { useAuth } from "../../composables/useAuth";
import { getStoredUserToken } from "../../utils/authSession";
import {
  createLandingBouquet,
  PAPER_PRESETS,
  RIBBON_PRESETS,
} from "../../composables/useLandingBouquet";
import LandingLoader from "../../components/landing/LandingLoader.vue";
import PlanAccountModal from "../../components/landing/PlanAccountModal.vue";
import PricingPlanCard from "../../components/pricing/PricingPlanCard.vue";
import {
  SUBSCRIPTION_PLANS,
  savePendingPlan,
} from "../../constants/subscriptionPlans";

gsap.registerPlugin(ScrollTrigger);

const router = useRouter();

// The landing route is public, so the router guard never loads the user here.
// A stored user token plus the persisted user record means a live session.
const { user: authUser } = useAuth();
const hasStoredSession = ref(false);
const isAuthenticated = computed(
  () => !!getStoredUserToken() && (!!authUser.value || hasStoredSession.value),
);

// Panel section refs
const heroSection = ref(null);
const storySection = ref(null);
const storyProgressFill = ref(null);
const statsSection = ref(null);
const plansSection = ref(null);
const plansViewport = ref(null);
const plansTrack = ref(null);
const plansProgressFill = ref(null);
const blogSection = ref(null);
const aboutSection = ref(null);

// BloomCraft's Vision and Mission, verbatim.
const VISION =
  "To create a trusted and innovative online platform for flower shops in Cavite by providing a convenient, personalized, and interactive floral shopping experience that connects customers with quality floral arrangements and reliable flower shop services, while helping businesses embrace modern digital solutions.";
const MISSION =
  "Our mission is to enhance the operations of flower shops by providing a reliable digital platform that simplifies inventory management, order processing, sales reporting, and customer communication. We aim to provide customers with a convenient and interactive way to explore, customize, and order floral arrangements while supporting flower shops in making better business decisions and delivering quality services.";
const aboutStatements = [
  { title: "Our Vision", words: VISION.split(" ") },
  { title: "Our Mission", words: MISSION.split(" ") },
];
const ctaSection = ref(null);
const footerSection = ref(null);

const flowerCanvas = ref(null);
const flowerStage = ref(null);
const blackoutLayer = ref(null);

// Bouquet story state
const storyStatic = ref(false);
const activeStep = ref(0);
const storySteps = [
  { key: "flowers", label: "Blooms" },
  { key: "wrapper", label: "Wrapper" },
  { key: "fillers", label: "Fillers" },
];
const paperSwatches = PAPER_PRESETS.map((color, i) => ({
  color,
  name: ["Kraft", "Ivory", "Linen", "Blush", "Mocha"][i],
}));
const innerSwatches = RIBBON_PRESETS.map((color, i) => ({
  color,
  name: ["Lilac", "Rose", "Sage", "Butter", "Plum"][i],
}));
const paperColor = ref(null);
const innerColor = ref(null);
let userPickedColor = false;

function pickPaper(color) {
  userPickedColor = true;
  paperColor.value = color;
  bouquet?.setPaperColor(color);
}

function pickInner(color) {
  userPickedColor = true;
  innerColor.value = color;
  bouquet?.setRibbonColor(color);
}

// Loading intro — shown once per browser session, driven by the real
// bouquet download progress, capped so a slow network never blocks the page.
const INTRO_SEEN_KEY = "bloomcraft_intro_seen";
const INTRO_MAX_WAIT_MS = 8000;
const showLoader = ref(false);
const loaderProgress = ref(0);
const loaderDone = ref(false);
const reducedMotionPref = ref(false);
let introTimeout = null;
const navScrolled = ref(false);
const navHidden = ref(false);
const showBackToTop = ref(false);
const scrollProgressFill = ref(null);
const backToTopRing = ref(null);
const RING_LENGTH = 2 * Math.PI * 24; // circumference of the r=24 ring
const mobileMenuOpen = ref(false);
const NAV_HIDE_AFTER = 120; // px from the top before the bar may hide
const NAV_SCROLL_DELTA = 8; // ignore tiny scroll jitter
let lastScrollY = 0;

function closeMobileMenu() {
  mobileMenuOpen.value = false;
}

function navigateTo(sectionId) {
  closeMobileMenu();
  scrollToSection(sectionId);
}

// While the mobile menu is open, Escape or a click outside the bar closes it.
function handleMenuDismiss(event) {
  if (event.type === "keydown" && event.key !== "Escape") return;
  if (event.type === "pointerdown" && event.target.closest(".navbar")) return;
  closeMobileMenu();
}

watch(mobileMenuOpen, (open) => {
  const method = open ? "addEventListener" : "removeEventListener";
  document[method]("keydown", handleMenuDismiss);
  document[method]("pointerdown", handleMenuDismiss);
});

// Reactive Data
const features = ref([
  {
    id: 1,
    icon: "🎨",
    title: "3D Customization",
    description:
      "Design your perfect bouquet in stunning 3D. Rotate, adjust colors, and see every detail before you buy.",
  },
  {
    id: 2,
    icon: "🤖",
    title: "AI Suggestions",
    description:
      "Not sure what to choose? Our AI recommends arrangements based on occasion, season, and your preferences.",
  },
  {
    id: 3,
    icon: "🏪",
    title: "Vendor Marketplace",
    description:
      "Connect with local florists and artisans. Support small businesses while getting fresh, quality flowers.",
  },
]);

const statsData = ref([
  { icon: "🌸", number: "340", label: "Arrangements sent this week" },
  // In production, wire this to a real vendor-presence feed rather than a static count.
  { icon: "🧑‍🌾", number: "62", label: "Vendor studios online now", live: true },
  { icon: "🚚", number: "48hr", label: "Average delivery window" },
  { icon: "⭐", number: "4.9/5", label: "Rating across 5,000+ orders" },
]);

const blogPosts = ref([
  { id: 1, title: "Seasonal Flower Guide for Spring" },
  { id: 2, title: "How to Care for Your Custom Bouquet" },
  { id: 3, title: "Meet Our Featured Vendor: Petal & Co" },
]);

const socialLinks = ref([
  { name: "Facebook", icon: "f", url: "#" },
  { name: "X", icon: "x", url: "#" },
  { name: "LinkedIn", icon: "in", url: "#" },
  { name: "Instagram", icon: "ig", url: "#" },
]);

const companyLinks = ref([
  { name: "About Us", url: "#about" },
  { name: "Contact", url: "#contact" },
  { name: "Careers", url: "#careers" },
  { name: "Press", url: "#press" },
]);

const supportLinks = ref([
  { name: "Help Center", url: "#help" },
  { name: "Terms of Service", url: "#terms" },
  { name: "Privacy Policy", url: "#privacy" },
  { name: "Shipping Info", url: "#shipping" },
]);

// Computed
const currentYear = computed(() => new Date().getFullYear());

// Methods (unchanged)
// Scroll to a y position (Lenis when active, native otherwise).
const scrollToY = (y) => {
  if (lenis) lenis.scrollTo(y);
  else window.scrollTo({ top: y, behavior: prefersReducedMotion ? "auto" : "smooth" });
};

// Keyboard focus moved somewhere off screen: jump there instantly, after the
// browser has finished its own focus scrolling.
const jumpToY = (y) => {
  requestAnimationFrame(() => {
    if (lenis) lenis.scrollTo(y, { immediate: true, force: true });
    else window.scrollTo({ top: y, behavior: "auto" });
  });
};

const scrollToElement = (element) => {
  if (lenis) lenis.scrollTo(element, { offset: -80 });
  else element.scrollIntoView({ behavior: prefersReducedMotion ? "auto" : "smooth" });
};

const scrollToSection = (sectionId) => {
  // Story steps live inside the pinned section, so jump to their scroll
  // position along the pin rather than to the element itself.
  if (storyTrigger && sectionId in STORY_ANCHORS) {
    const { start, end } = storyTrigger;
    scrollToY(start + (end - start) * STORY_ANCHORS[sectionId]);
    return;
  }
  const element = document.getElementById(sectionId);
  if (element) scrollToElement(element);
};

// ---- Subscription plans ----
// Logged-in vendors go straight to the existing checkout on /pricing. Guests
// are asked whether they have a vendor account; the chosen plan is kept so
// their next vendor login lands on that checkout (see useAuth).
const subscriptionPlans = SUBSCRIPTION_PLANS;
const accountModalPlan = ref(null);
const planNotice = ref("");

function storedUserRole() {
  try {
    return JSON.parse(localStorage.getItem("user") || "null")?.role || null;
  } catch (_) {
    return null;
  }
}

function handlePlanSelect(plan) {
  planNotice.value = "";
  if (getStoredUserToken()) {
    const role = authUser.value?.role || storedUserRole();
    if (role === "vendor") {
      router.push({ path: "/pricing", query: { plan: plan.key } });
      return;
    }
    if (role) {
      planNotice.value =
        "Subscriptions are for vendor accounts. Register your flower shop to choose a plan.";
      return;
    }
  }
  accountModalPlan.value = plan;
}

// Freeze smooth scrolling behind the open modal.
watch(accountModalPlan, (plan) => {
  if (plan) lenis?.stop();
  else lenis?.start();
});

function continueWithLogin() {
  savePendingPlan(accountModalPlan.value?.key);
  accountModalPlan.value = null;
  router.push("/guest/login");
}

function continueWithRegister() {
  savePendingPlan(accountModalPlan.value?.key);
  accountModalPlan.value = null;
  router.push("/guest/vendor_register");
}

const handleLearnMore = (type) => {
  console.log("Learn more about:", type);
  router.push("/guest/register");
};

const readBlog = (postId) => {
  console.log("Reading blog post:", postId);
};

const handleFooterLink = (url) => {
  if (url === "#about") {
    scrollToSection("about");
    return;
  }
  console.log("Footer link clicked:", url);
};

// ==========================================================
// PERSISTENT 3D BOUQUET — the real bouquet model (see
// composables/useLandingBouquet.js), its journey across the
// page, and pointer interaction.
// ==========================================================
let bouquet = null;
let rig = null; // bouquet.rig — position/rotation/scale driven by scroll
let camera = null;
let lenis = null;
let prefersReducedMotion = false;

let scrollIdleTimeout = null;

function initThreeScene() {
  const canvas = flowerCanvas.value;
  if (!canvas) return;

  prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
  ).matches;
  storyStatic.value = prefersReducedMotion;

  gsap.set(canvas, { autoAlpha: 0 });
  bouquet = createLandingBouquet(canvas, {
    reducedMotion: prefersReducedMotion,
    onProgress: (ratio) => {
      loaderProgress.value = ratio;
    },
  });
  rig = bouquet.rig;
  camera = bouquet.camera;

  bouquet.ready
    .then(() => {
      // Rebuild now that part positions are known, so the story can zoom
      // onto the real flowers, wrapper and fillers.
      setupJourney();
      ScrollTrigger.refresh();
      gsap.to(canvas, { autoAlpha: 1, duration: prefersReducedMotion ? 0 : 1.2, ease: "power2.out" });
    })
    .catch((error) => {
      // The page stays fully usable without the model: the glow, petals and
      // all content still render.
      console.error("Failed to load the landing bouquet model:", error);
    })
    .finally(() => {
      loaderDone.value = true;
    });
}

function startIntro() {
  let seen = false;
  try {
    seen = sessionStorage.getItem(INTRO_SEEN_KEY) === "1";
  } catch (_) {
    // Storage can be unavailable (private mode); just show the intro.
  }

  if (seen) {
    onIntroFinished();
    return;
  }

  showLoader.value = true;
  document.documentElement.style.overflow = "hidden";
  introTimeout = setTimeout(() => {
    loaderDone.value = true;
  }, INTRO_MAX_WAIT_MS);
}

function onIntroFinished() {
  clearTimeout(introTimeout);
  showLoader.value = false;
  document.documentElement.style.overflow = "";
  try {
    sessionStorage.setItem(INTRO_SEEN_KEY, "1");
  } catch (_) {
    // Ignore storage failures.
  }
  pageCtx?.add(() => setupSectionReveals());
  initSmoothScroll();
  ScrollTrigger.refresh();
}

// ==========================================================
// BOUQUET JOURNEY — one camera-driven story across the page.
// The bouquet stays at the origin; the camera moves around it.
// Everything is created inside a gsap.context so a resize can
// revert and rebuild it cleanly.
// ==========================================================
const STORY_LENGTH = 10; // timeline units
const STEP_TIMES = [0.2, 3.2, 5.8, 8.2]; // when each story step enters
const STORY_ANCHORS = { features: 0.18, "how-it-works": 0.68 }; // nav targets (progress)
let journeyCtx = null;
let pageCtx = null; // hero intro + background parallax
let storyTrigger = null;
let plansTrigger = null;

function skipToContent() {
  const main = document.getElementById("main-content");
  if (!main) return;
  scrollToElement(main);
  main.focus({ preventScroll: true });
}

// Keyboard focus landing in a story step that is not on screen: scroll the
// pinned story to that step.
function revealFocusedStep(event) {
  const step = event.target.closest(".story-step");
  if (!step || !storyTrigger) return;
  const index = Number(step.dataset.step);
  if (index === activeStep.value) return;
  const { start, end } = storyTrigger;
  jumpToY(start + (end - start) * ((STEP_TIMES[index] + 0.5) / STORY_LENGTH));
}

// Keyboard users tabbing through the pinned plans track: scroll the page so
// the focused card is the one in view.
function revealFocusedPlan(event) {
  const card = event.target.closest(".plans-card");
  const track = plansTrack.value;
  const viewport = plansViewport.value;
  if (!card || !track || !viewport) return;
  viewport.scrollLeft = 0; // the browser may scroll the clipped viewport itself
  if (!plansTrigger) return;
  const max = track.scrollWidth - viewport.clientWidth;
  if (max <= 0) return;
  const ratio = Math.min(card.offsetLeft / max, 1);
  const { start, end } = plansTrigger;
  jumpToY(start + (end - start) * ratio);
}

function halfVisible(distance) {
  const halfH = Math.tan((camera.fov * Math.PI) / 360) * distance;
  return { halfH, halfW: halfH * camera.aspect };
}

// Camera frame that keeps `target` at a fixed screen offset. sx / sy are
// fractions of the half-viewport (+sx = right of centre, +sy = above centre).
// `lift` raises the camera slightly so it looks down onto the target.
function frameAt(target, distance, sx = 0, sy = 0, lift = 0) {
  const { halfH, halfW } = halfVisible(distance);
  const ox = halfW * sx;
  const oy = halfH * sy;
  return {
    x: target.x - ox,
    y: target.y - oy + lift,
    z: target.z + distance,
    tx: target.x - ox,
    ty: target.y - oy,
    tz: target.z,
  };
}

function partFrame(key, scale, sx, sy, minDistance, maxDistance = 4.6) {
  const part = bouquet?.parts[key];
  if (!part || !part.meshes.length) return null;
  const center = part.center.clone().multiplyScalar(scale);
  const size = Math.max(part.size.x, part.size.y, part.size.z) * scale;
  const distance = Math.min(Math.max(size * 2.4, minDistance), maxDistance);
  return frameAt(center, distance, sx, sy, 0.25);
}

function stepAt(time) {
  let index = 0;
  STEP_TIMES.forEach((start, i) => {
    if (time >= start - 0.2) index = i;
  });
  return index;
}

function cyclePaper(index) {
  if (userPickedColor) return;
  const paper = PAPER_PRESETS[index];
  const inner = RIBBON_PRESETS[index];
  if (paperColor.value === paper) return;
  paperColor.value = paper;
  innerColor.value = inner;
  bouquet?.setPaperColor(paper);
  bouquet?.setRibbonColor(inner);
}

function setupJourney() {
  journeyCtx?.revert();
  storyTrigger = null;
  plansTrigger = null;
  if (!bouquet) return;

  const isMobile = window.innerWidth < 968;
  const scale = isMobile ? 0.62 : 0.95;
  // Where the bouquet sits on screen in the hero and during the story.
  const heroSx = isMobile ? 0 : 0.42;
  const heroSy = isMobile ? -0.42 : 0;
  const storySx = isMobile ? 0 : 0.36;
  const storySy = isMobile ? 0.3 : 0;
  const origin = { x: 0, y: 0, z: 0 };
  const heroFrame = frameAt(origin, 6, heroSx, heroSy);

  journeyCtx = gsap.context(() => {
    const stage = flowerStage.value;
    gsap.set(rig.position, { x: 0, y: 0, z: 0 });
    gsap.set(rig.scale, { x: scale, y: scale, z: scale });
    gsap.set(rig.rotation, { x: 0, y: prefersReducedMotion ? 0 : -0.35, z: 0 });
    gsap.set(bouquet.view, heroFrame);
    gsap.set(bouquet.focus, { flowers: 1, wrapper: 1, fillers: 1 });

    // Pause pointer tilt while scrolling.
    ScrollTrigger.create({
      start: 0,
      end: "max",
      onUpdate: () => {
        bouquet.setInteractionPaused(true);
        clearTimeout(scrollIdleTimeout);
        scrollIdleTimeout = setTimeout(() => bouquet?.setInteractionPaused(false), 150);
      },
    });

    if (prefersReducedMotion) {
      if (stage && heroSection.value) {
        ScrollTrigger.create({
          trigger: heroSection.value,
          start: "bottom 40%",
          onEnter: () => {
            gsap.set(stage, { autoAlpha: 0 });
            bouquet.setActive(false);
          },
          onLeaveBack: () => {
            gsap.set(stage, { autoAlpha: 1 });
            bouquet.setActive(true);
          },
        });
      }
      return;
    }

    // ---- Hero exit: copy lifts away, camera dollies in ----
    const hero = heroSection.value;
    if (hero) {
      const heroTl = gsap.timeline({
        scrollTrigger: { trigger: hero, start: "top top", end: "bottom top", scrub: 1 },
      });
      heroTl.to(hero.querySelector(".hero-content"), { yPercent: -18, autoAlpha: 0, ease: "power1.in" }, 0);
      heroTl.to(hero.querySelector(".hero-scroll-cue"), { autoAlpha: 0, ease: "none", duration: 0.3 }, 0);
      heroTl.to(hero.querySelector(".hero-halo"), { scale: 1.35, autoAlpha: 0, ease: "power1.in" }, 0);
      heroTl.fromTo(bouquet.view, { ...heroFrame }, { ...frameAt(origin, 5, storySx, storySy), ease: "power1.inOut" }, 0);
      heroTl.to(rig.rotation, { y: 0, ease: "power1.inOut" }, 0);
    }

    // ---- Pinned bouquet story ----
    const story = storySection.value;
    if (story) {
      const steps = gsap.utils.toArray(story.querySelectorAll(".story-step"));
      gsap.set(steps, { opacity: 0, y: 40 });

      const overview = frameAt(origin, 5, storySx, storySy);
      const flowers = partFrame("flowers", scale, storySx, storySy, ...(isMobile ? [4.2, 5.4] : [2.2])) || overview;
      const wrapper = partFrame("wrapper", scale, storySx, storySy, ...(isMobile ? [5, 6] : [3])) || overview;
      const fillers = partFrame("fillers", scale, storySx, storySy, ...(isMobile ? [4.6, 5.8] : [2.4])) || overview;
      const finale = frameAt(origin, 5.6, storySx, storySy);
      const paperProxy = { i: 0 };

      const tl = gsap.timeline({
        defaults: { ease: "power2.inOut" },
        // Driven by the scrubbed playhead (not raw scroll) so the index and
        // progress line stay in sync with what is on screen.
        onUpdate: () => {
          activeStep.value = stepAt(tl.time());
          if (storyProgressFill.value) {
            storyProgressFill.value.style.transform = `scaleX(${tl.progress()})`;
          }
        },
        scrollTrigger: {
          trigger: story,
          start: "top top",
          end: isMobile ? "+=320%" : "+=440%",
          pin: true,
          scrub: 1,
          anticipatePin: 1,
        },
      });

      // Step copy cross-fades.
      steps.forEach((step, i) => {
        tl.to(step, { opacity: 1, y: 0, duration: 0.5, ease: "power2.out" }, STEP_TIMES[i]);
        if (i < steps.length - 1) {
          tl.to(step, { opacity: 0, y: -40, duration: 0.5, ease: "power2.in" }, STEP_TIMES[i + 1] - 0.6);
        }
      });

      // 01 Blooms: zoom into the flowers, soften everything else.
      tl.fromTo(bouquet.view, { ...overview }, { ...flowers, duration: 1.4 }, 0.4);
      tl.to(bouquet.focus, { wrapper: 0.25, fillers: 0.35, duration: 1 }, 0.4);

      // 02 Wrapper: pull back and orbit to the paper; cycle swatches.
      tl.to(bouquet.view, { ...wrapper, duration: 1.4 }, 3);
      tl.to(rig.rotation, { y: 0.55, duration: 1.6 }, 3);
      tl.to(bouquet.focus, { flowers: 0.35, wrapper: 1, fillers: 0.3, duration: 1 }, 3);
      tl.to(
        paperProxy,
        {
          i: PAPER_PRESETS.length - 0.01,
          duration: 2,
          ease: "none",
          onUpdate: () => cyclePaper(Math.floor(paperProxy.i)),
        },
        3.6,
      );

      // 03 Fillers: zoom onto leaves and small plants.
      tl.to(bouquet.view, { ...fillers, duration: 1.4 }, 5.6);
      tl.to(rig.rotation, { y: -0.45, duration: 1.6 }, 5.6);
      tl.to(bouquet.focus, { flowers: 0.35, wrapper: 0.25, fillers: 1, duration: 1 }, 5.6);

      // Reveal: full bouquet, one slow turn.
      tl.to(bouquet.view, { ...finale, duration: 1.6 }, 8);
      tl.to(bouquet.focus, { flowers: 1, wrapper: 1, fillers: 1, duration: 1 }, 8);
      tl.to(rig.rotation, { y: Math.PI * 2, duration: 2, ease: "power1.inOut" }, 8);
      tl.set({}, {}, STORY_LENGTH);

      storyTrigger = tl.scrollTrigger;
    }

    // ---- After the story the bouquet steps aside for content ----
    const stats = statsSection.value;
    if (stats && stage) {
      gsap.to(stage, {
        autoAlpha: 0,
        ease: "none",
        scrollTrigger: { trigger: stats, start: "top 85%", end: "top 35%", scrub: 1 },
      });
      // Nothing to draw while it is hidden: pause the render loop until the
      // About section brings it back.
      const aboutEl = aboutSection.value;
      if (aboutEl) {
        ScrollTrigger.create({
          trigger: stats,
          start: "top 20%",
          endTrigger: aboutEl,
          end: "top 85%",
          onToggle: (self) => bouquet?.setActive(!self.isActive),
        });
      }
    }

    // ---- Plans: vertical scroll drives the horizontal card track ----
    const plansEl = plansSection.value;
    const track = plansTrack.value;
    const viewport = plansViewport.value;
    if (plansEl && track && viewport && !isMobile) {
      const distance = () => Math.max(track.scrollWidth - viewport.clientWidth, 0);
      if (distance() > 0) {
        const plansTl = gsap.timeline({
          scrollTrigger: {
            trigger: plansEl,
            start: "top top",
            end: () => `+=${distance()}`,
            pin: true,
            scrub: 1,
            invalidateOnRefresh: true,
          },
          onUpdate: () => {
            if (plansProgressFill.value) {
              plansProgressFill.value.style.transform = `scaleX(${plansTl.progress()})`;
            }
          },
        });
        plansTl.to(track, { x: () => -distance(), ease: "none" });
        plansTrigger = plansTl.scrollTrigger;
      }
    }

    // ---- About: words brighten as you read; bouquet becomes a soft backdrop ----
    const about = aboutSection.value;
    if (about) {
      about.querySelectorAll(".about-card").forEach((card) => {
        gsap.fromTo(
          card.querySelectorAll(".about-word"),
          { opacity: 0.16 },
          {
            opacity: 1,
            ease: "none",
            stagger: 0.05,
            scrollTrigger: { trigger: card, start: "top 80%", end: "bottom 45%", scrub: 1 },
          },
        );
      });
      gsap.fromTo(
        about.querySelector(".about-rule"),
        { scaleX: 0 },
        {
          scaleX: 1,
          ease: "none",
          scrollTrigger: { trigger: about, start: "center 70%", end: "bottom 60%", scrub: 1 },
        },
      );
      if (stage) {
        gsap.fromTo(
          stage,
          { autoAlpha: 0, filter: "blur(0px)" },
          {
            autoAlpha: 0.22,
            filter: isMobile ? "blur(0px)" : "blur(8px)",
            ease: "none",
            immediateRender: false,
            scrollTrigger: { trigger: about, start: "top 80%", end: "top 30%", scrub: 1 },
          },
        );
      }
    }

    // ---- CTA: the bouquet returns, centred above the heading ----
    const cta = ctaSection.value;
    if (cta && stage) {
      const ctaTl = gsap.timeline({
        scrollTrigger: { trigger: cta, start: "top 90%", end: "top 25%", scrub: 1 },
      });
      ctaTl.fromTo(
        stage,
        { autoAlpha: about ? 0.22 : 0, filter: about && !isMobile ? "blur(8px)" : "blur(0px)" },
        { autoAlpha: 1, filter: "blur(0px)", ease: "none", immediateRender: false },
        0,
      );
      ctaTl.fromTo(
        bouquet.view,
        { ...frameAt(origin, 5.6, storySx, storySy) },
        { ...frameAt(origin, isMobile ? 8.4 : 7.5, 0, isMobile ? 0.5 : 0.42), ease: "power2.out", immediateRender: false },
        0,
      );
    }

    // ---- Blackout into the footer ----
    // An espresso iris opens from the CTA while the bouquet shrinks into the
    // dark; then the footer columns rise in. Rendering pauses once covered.
    const footer = footerSection.value;
    if (footer) {
      const tl = gsap.timeline({
        scrollTrigger: {
          trigger: footer,
          start: "top bottom",
          end: "top 30%",
          scrub: 1,
          onLeave: () => bouquet?.setActive(false),
          onEnterBack: () => bouquet?.setActive(true),
        },
      });
      const blackout = blackoutLayer.value;
      if (blackout) {
        tl.fromTo(
          blackout,
          { clipPath: "circle(0% at 50% 62%)" },
          { clipPath: "circle(150% at 50% 62%)", ease: "power2.in", duration: 0.7 },
          0,
        );
      }
      if (stage) tl.to(stage, { autoAlpha: 0, ease: "power1.in", duration: 0.6, immediateRender: false }, 0);
      tl.to(rig.scale, { x: scale * 0.55, y: scale * 0.55, z: scale * 0.55, ease: "power1.in", duration: 0.6 }, 0);
      tl.to(".landing-page", { backgroundColor: "#1b1612", ease: "none", duration: 0.12 }, 0.6);
      tl.to(".navbar .nav-toggle-bar", { backgroundColor: "#faf8f5", ease: "none" }, 0);
      tl.fromTo(
        footer.querySelectorAll(".footer-brand, .footer-section, .footer-bottom"),
        { autoAlpha: 0, y: 48 },
        { autoAlpha: 1, y: 0, ease: "power2.out", stagger: 0.08, duration: 0.4 },
        0.5,
      );
      tl.to(".cta-panel h2", { color: "#faf8f5", ease: "none", duration: 0.2 }, 0.22);
      tl.to(".btn-cta", { backgroundColor: "#faf8f5", color: "#1b1612", ease: "none", duration: 0.2 }, 0.22);
      tl.to(".navbar", { backgroundColor: "#1b1612", borderColor: "rgba(250, 248, 245, 0.15)", ease: "none" }, 0);
      tl.to(".navbar .logo, .navbar .nav-links a", { color: "#faf8f5", ease: "none" }, 0);
      tl.to(".navbar .btn-login", { color: "#faf8f5", borderColor: "rgba(250, 248, 245, 0.35)", ease: "none" }, 0);
      tl.to(".navbar .btn-register", { backgroundColor: "#faf8f5", color: "#1b1612", ease: "none" }, 0);
    }
  });
}

function setupSectionReveals() {
  // The flower's scroll journey is the page's one orchestrated motion moment.
  // Every other section renders in place — only the hero gets an entrance,
  // played once on load rather than re-triggered as the visitor scrolls.
  if (!heroSection.value) return;

  const lines = heroSection.value.querySelectorAll(".hero-title .line-inner");
  const reveals = heroSection.value.querySelectorAll(".hero-reveal");
  const halo = heroSection.value.querySelector(".hero-halo");

  if (prefersReducedMotion) {
    gsap.set([lines, reveals, halo], { clearProps: "all" });
    return;
  }

  gsap
    .timeline({ delay: 0.1 })
    .fromTo(
      lines,
      { yPercent: 110 },
      { yPercent: 0, duration: 1.1, ease: "expo.out", stagger: 0.12 },
    )
    .fromTo(
      reveals,
      { autoAlpha: 0, y: 18 },
      { autoAlpha: 1, y: 0, duration: 0.8, ease: "power2.out", stagger: 0.08 },
      "-=0.75",
    )
    .fromTo(
      halo,
      { scale: 0.8, autoAlpha: 0 },
      { scale: 1, autoAlpha: 1, duration: 1.4, ease: "power3.out" },
      0,
    );
}

function setupBackgroundParallax() {
  if (prefersReducedMotion) return;
  gsap.to(".blob-rose", {
    yPercent: 35,
    ease: "none",
    scrollTrigger: {
      trigger: ".landing-page",
      start: "top top",
      end: "bottom bottom",
      scrub: 0.8,
    },
  });
  gsap.to(".blob-sage", {
    yPercent: -25,
    ease: "none",
    scrollTrigger: {
      trigger: ".landing-page",
      start: "top top",
      end: "bottom bottom",
      scrub: 0.8,
    },
  });
}

function updateScrollProgress(y) {
  const max = document.documentElement.scrollHeight - window.innerHeight;
  const progress = max > 0 ? Math.min(Math.max(y / max, 0), 1) : 0;
  if (scrollProgressFill.value) {
    scrollProgressFill.value.style.transform = `scaleX(${progress})`;
  }
  if (backToTopRing.value) {
    backToTopRing.value.style.strokeDashoffset = String(RING_LENGTH * (1 - progress));
  }
  showBackToTop.value = y > window.innerHeight;
}

function scrollToTop() {
  if (lenis) lenis.scrollTo(0, { duration: 1.6 });
  else window.scrollTo({ top: 0, behavior: prefersReducedMotion ? "auto" : "smooth" });
  // Return keyboard focus to the start of the page.
  document.querySelector(".navbar .logo")?.focus({ preventScroll: true });
}

function handleWindowScroll() {
  const y = window.scrollY;
  navScrolled.value = y > 24;
  updateScrollProgress(y);

  if (y <= NAV_HIDE_AFTER || mobileMenuOpen.value) {
    navHidden.value = false;
    lastScrollY = y;
    return;
  }
  const delta = y - lastScrollY;
  if (Math.abs(delta) < NAV_SCROLL_DELTA) return;
  navHidden.value = delta > 0;
  lastScrollY = y;
}

function handlePointerMove(event) {
  bouquet?.setPointer(
    (event.clientX / window.innerWidth) * 2 - 1,
    (event.clientY / window.innerHeight) * 2 - 1,
  );
}

let resizeTimeout = null;
let lastWidth = 0;

function handleResize() {
  if (!bouquet) return;
  bouquet.resize(window.innerWidth, window.innerHeight);

  // Mobile browsers fire resize when the URL bar shows/hides; only rebuild
  // the scroll story when the width actually changes.
  if (window.innerWidth === lastWidth) return;
  lastWidth = window.innerWidth;
  clearTimeout(resizeTimeout);
  resizeTimeout = setTimeout(() => {
    setupJourney();
    ScrollTrigger.refresh();
  }, 200);
}

function lenisTick(time) {
  lenis?.raf(time * 1000);
}

function initSmoothScroll() {
  if (lenis || prefersReducedMotion) return;
  lenis = new Lenis({
    duration: 1.2,
    easing: (t) => 1 - Math.pow(1 - t, 3),
  });
  lenis.on("scroll", ScrollTrigger.update);
  gsap.ticker.add(lenisTick);
  gsap.ticker.lagSmoothing(0);
}


onMounted(() => {
  hasStoredSession.value = !!localStorage.getItem("user");
  reducedMotionPref.value = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
  ).matches;
  lastWidth = window.innerWidth;
  initThreeScene();
  setupJourney();
  pageCtx = gsap.context(() => setupBackgroundParallax());
  startIntro();
  window.addEventListener("mousemove", handlePointerMove, { passive: true });
  window.addEventListener("resize", handleResize);
  window.addEventListener("scroll", handleWindowScroll, { passive: true });
  handleWindowScroll();
});

onBeforeUnmount(() => {
  window.removeEventListener("mousemove", handlePointerMove);
  window.removeEventListener("resize", handleResize);
  window.removeEventListener("scroll", handleWindowScroll);
  document.removeEventListener("keydown", handleMenuDismiss);
  document.removeEventListener("pointerdown", handleMenuDismiss);
  clearTimeout(scrollIdleTimeout);
  clearTimeout(introTimeout);
  clearTimeout(resizeTimeout);
  document.documentElement.style.overflow = "";

  gsap.ticker.remove(lenisTick);
  gsap.ticker.lagSmoothing(500, 33); // GSAP defaults
  if (lenis) lenis.destroy();
  lenis = null;
  // Revert only this page's animations and triggers.
  journeyCtx?.revert();
  pageCtx?.revert();
  if (bouquet) bouquet.dispose();
});
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap");

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

/* Same body reset the pricing page applies; removes the UA 8px margin. */
:global(body) {
  margin: 0;
}

.landing-page {
  /* Dim-white base: softer than pure white, warm like petals */
  --ivory: #f4f1ec;
  --ivory-deep: #ebe5dd;
  --ink: #1e1b18;
  --ink-soft: #6e6761;
  --rosewood: #b86558;
  --rosewood-dark: #8e443a;
  --gold: #d4b27a;
  --espresso: #1b1612;
  --line: rgba(30, 27, 24, 0.06);

  /* Glassmorphism templates */
  --ivory-glass: rgba(250, 248, 245, 0.7);
  --ivory-deep-glass: rgba(242, 237, 231, 0.7);
  --espresso-glass: rgba(27, 22, 18, 0.85);

  position: relative;
  background: var(--ivory);
  color: var(--ink);
  font-family:
    "Poppins",
    -apple-system,
    sans-serif;
  font-weight: 300;
  line-height: 1.7;
  overflow-x: hidden;
}

.landing-page h1,
.landing-page h2,
.landing-page h3,
.landing-page .stat-number {
  font-family: "Montserrat", sans-serif;
  font-weight: 800;
  letter-spacing: -0.02em;
}

.hero-kicker {
  display: inline-block;
  font-family: "Poppins", sans-serif;
  font-size: 16px;
  font-weight: 500;
  font-style: italic;
  color: var(--rosewood-dark);
  margin-bottom: 18px;
}

/* Skip link: visible only when focused */
.skip-link {
  position: fixed;
  top: 12px;
  left: 12px;
  z-index: 3100;
  padding: 10px 18px;
  border-radius: 999px;
  background: var(--ink);
  color: var(--ivory);
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  transform: translateY(-160%);
  transition: transform 0.25s ease;
}

.skip-link:focus {
  transform: none;
  outline: 2px solid var(--rosewood);
  outline-offset: 3px;
}

.scroll-story-container:focus {
  outline: none;
}

/* ============================================================
   SCROLL PROGRESS + BACK TO TOP
============================================================= */
.scroll-progress {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 1100;
  height: 2px;
  pointer-events: none;
}

.scroll-progress-fill {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, var(--rosewood), var(--gold));
  transform: scaleX(0);
  transform-origin: left;
}

.back-to-top {
  position: fixed;
  right: calc(24px + env(safe-area-inset-right, 0px));
  bottom: calc(24px + env(safe-area-inset-bottom, 0px));
  z-index: 1050;
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  padding: 0;
  border: none;
  border-radius: 50%;
  background: rgba(250, 248, 245, 0.92);
  box-shadow: 0 10px 28px rgba(30, 27, 24, 0.14);
  color: var(--ink);
  cursor: pointer;
  opacity: 0;
  visibility: hidden;
  transform: translateY(12px);
  transition:
    opacity 0.35s ease,
    transform 0.35s cubic-bezier(0.16, 1, 0.3, 1),
    visibility 0.35s,
    background 0.3s ease;
}

.back-to-top.is-visible {
  opacity: 1;
  visibility: visible;
  transform: none;
}

.back-to-top:hover {
  background: #ffffff;
}

.back-to-top:focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 3px;
}

.btt-ring {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  transform: rotate(-90deg);
}

.btt-ring-track,
.btt-ring-fill {
  fill: none;
  stroke-width: 2;
}

.btt-ring-track {
  stroke: rgba(30, 27, 24, 0.08);
}

.btt-ring-fill {
  stroke: var(--rosewood);
  stroke-linecap: round;
  stroke-dasharray: 150.8; /* 2πr, r = 24 */
  stroke-dashoffset: 150.8;
}

.btt-arrow {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform 0.3s ease;
}

.back-to-top:hover .btt-arrow {
  transform: translateY(-2px);
}

@media (max-width: 640px) {
  .back-to-top {
    right: calc(16px + env(safe-area-inset-right, 0px));
    bottom: calc(16px + env(safe-area-inset-bottom, 0px));
    width: 46px;
    height: 46px;
  }
}

/* Ambient dynamic background */
.bg-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(80px);
  pointer-events: none;
  z-index: 0;
}

.blob-rose {
  top: -8%;
  right: -5%;
  width: 48vw;
  height: 48vw;
  background: radial-gradient(
    circle,
    rgba(184, 101, 88, 0.22),
    transparent 70%
  );
}

.blob-sage {
  top: 50%;
  left: -10%;
  width: 42vw;
  height: 42vw;
  background: radial-gradient(
    circle,
    rgba(156, 181, 145, 0.16),
    transparent 70%
  );
}

.grain-overlay {
  position: fixed;
  inset: 0;
  z-index: 2000;
  pointer-events: none;
  opacity: 0.04;
  mix-blend-mode: overlay;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
}

/* ============================================================
   PERSISTENT FLOWER CANVAS LAYER
============================================================= */
.flower-stage-fixed {
  position: fixed;
  inset: 0;
  z-index: 5;
  pointer-events: none;
  overflow: hidden;
}

.flower-canvas {
  width: 100%;
  height: 100%;
  display: block;
}

/* Blackout iris (Stage 6): sits above the bouquet, below the content */
.blackout {
  position: fixed;
  inset: 0;
  z-index: 8;
  background: var(--espresso);
  clip-path: circle(0% at 50% 62%);
  pointer-events: none;
}

/* Navigation — full-width at top, floating pill when scrolled */
.navbar {
  position: fixed;
  /* Always centred so width/radius can animate without jumping */
  top: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  max-width: 9999px; /* effectively unrestricted at top */
  background: var(--ivory);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  padding: 1.6rem 6%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 1000;
  border-radius: 0; /* flat at top */
  border: 1px solid transparent;
  box-shadow: none;
  transition:
    transform 0.5s cubic-bezier(0.16, 1, 0.3, 1),
    top 0.5s cubic-bezier(0.16, 1, 0.3, 1),
    width 0.5s cubic-bezier(0.16, 1, 0.3, 1),
    max-width 0.5s cubic-bezier(0.16, 1, 0.3, 1),
    padding 0.5s cubic-bezier(0.16, 1, 0.3, 1),
    border-radius 0.5s cubic-bezier(0.16, 1, 0.3, 1),
    border-color 0.5s cubic-bezier(0.16, 1, 0.3, 1),
    background 0.5s cubic-bezier(0.16, 1, 0.3, 1),
    box-shadow 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}

/* Hidden while scrolling down; keyboard focus always brings it back */
.navbar--hidden {
  transform: translate(-50%, calc(-100% - 32px));
}

.navbar--hidden:focus-within {
  transform: translateX(-50%);
}

/* Scrolled state — floating pill */
.navbar--scrolled {
  top: 20px;
  width: calc(100% - 48px);
  max-width: 1240px;
  padding: 0.72rem 2rem;
  border-radius: 999px;
  background: rgba(250, 248, 245, 0.86);
  border-color: rgba(30, 27, 24, 0.12);
  box-shadow:
    0 8px 40px rgba(30, 27, 24, 0.1),
    0 2px 8px rgba(30, 27, 24, 0.06);
}

.logo {
  display: flex;
  align-items: center;
  gap: 12px;
  font-family: "Montserrat", sans-serif;
  font-size: 23px;
  font-weight: 700;
  color: var(--ink);
  text-decoration: none;
  letter-spacing: -0.02em;
}

.nav-links {
  display: flex;
  gap: 36px;
  align-items: center;
}

.nav-links a {
  position: relative;
  color: var(--ink);
  text-decoration: none;
  font-size: 14.5px;
  font-weight: 500;
  padding-bottom: 4px;
  transition: color 0.3s;
}

.nav-links a::after {
  content: "";
  position: absolute;
  left: 0;
  bottom: 0;
  width: 0;
  height: 1.5px;
  background: var(--rosewood);
  transition: width 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.nav-links a:hover {
  color: var(--rosewood);
}

.nav-links a:hover::after {
  width: 100%;
}

.nav-buttons {
  display: flex;
  align-items: center;
  gap: 16px;
}

.nav-links a:focus-visible,
.nav-buttons a:focus-visible,
.logo:focus-visible,
.nav-toggle:focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 4px;
  border-radius: 6px;
}

/* Mobile menu toggle (shown below 968px) */
.nav-toggle {
  display: none;
  position: relative;
  width: 42px;
  height: 42px;
  border: 1px solid rgba(30, 27, 24, 0.12);
  border-radius: 50%;
  background: transparent;
  cursor: pointer;
}

.nav-toggle-bar {
  position: absolute;
  left: 12px;
  right: 12px;
  height: 1.5px;
  background: currentColor;
  color: var(--ink);
  transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.nav-toggle-bar:first-child {
  transform: translateY(-4px);
}

.nav-toggle-bar:last-child {
  transform: translateY(4px);
}

.navbar--menu-open .nav-toggle-bar:first-child {
  transform: rotate(45deg);
}

.navbar--menu-open .nav-toggle-bar:last-child {
  transform: rotate(-45deg);
}

.nav-menu-login {
  display: none;
}

.btn-login {
  padding: 10px 28px;
  background: transparent;
  color: var(--ink);
  border: 1px solid var(--line);
  border-radius: 999px;
  font-family: "Poppins", sans-serif;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
  text-decoration: none;
  display: inline-block;
}

.btn-login:hover {
  border-color: var(--ink);
  transform: translateY(-2px);
}

.btn-register {
  padding: 10px 28px;
  background: var(--ink);
  color: var(--ivory);
  border: none;
  border-radius: 999px;
  font-family: "Poppins", sans-serif;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
  text-decoration: none;
  display: inline-block;
}

.btn-register:hover {
  background: var(--rosewood);
  transform: translateY(-2px);
  box-shadow: 0 10px 24px rgba(184, 101, 88, 0.25);
}

/* ============================================================
   PANELS SCROLL STORY LAYOUT
============================================================= */
.scroll-story-container {
  position: relative;
  z-index: 10;
  width: 100%;
}

.panel-section {
  position: relative;
  min-height: 100vh;
  width: 100%;
  display: flex;
  align-items: center;
  padding: 120px 6%;
  overflow: hidden;
  background: transparent;
}

.panel-container {
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 80px;
  align-items: center;
  z-index: 15;
}

.panel-content {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.text-left-side {
  grid-column: 1;
}

.text-right-side {
  grid-column: 2;
}

.spacer-left {
  grid-column: 1;
}

.spacer-right {
  grid-column: 2;
}

.panel-visual {
  height: 100%;
  min-height: 380px;
  pointer-events: none;
}

/* Hero Panel */
.hero-panel {
  min-height: 100vh;
  min-height: 100svh;
  padding-top: 150px;
}

.hero-content {
  will-change: transform, opacity;
}

/* Soft gold halo framing the bouquet on the right */
.hero-halo {
  position: absolute;
  top: 50%;
  right: 6%;
  width: min(44vw, 620px);
  aspect-ratio: 1;
  transform: translateY(-50%);
  pointer-events: none;
  z-index: 0;
}

.halo-ring {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  border: 1px solid rgba(212, 178, 122, 0.45);
}

.halo-ring--inner {
  inset: 9%;
  border-style: dashed;
  border-color: rgba(184, 101, 88, 0.22);
  animation: haloSpin 60s linear infinite;
}

@keyframes haloSpin {
  to {
    transform: rotate(360deg);
  }
}

.hero-title {
  font-size: clamp(46px, 6.4vw, 92px);
  line-height: 1.02;
  margin-bottom: 28px;
  color: var(--ink);
}

.hero-title .line {
  display: block;
  overflow: hidden;
  padding-bottom: 0.08em;
}

.hero-title .line-inner {
  display: block;
}

.hero-title .highlight {
  color: var(--rosewood);
  background: linear-gradient(120deg, var(--rosewood) 30%, var(--gold) 90%);
  -webkit-background-clip: text;
  background-clip: text;
  -webkit-text-fill-color: transparent;
}

.hero-desc {
  font-size: 18px;
  color: var(--ink-soft);
  margin-bottom: 36px;
  max-width: 500px;
  line-height: 1.8;
}

.btn-hero {
  padding: 16px 44px;
  font-size: 15px;
}

.hero-scroll-cue {
  position: absolute;
  left: 50%;
  bottom: 36px;
  transform: translateX(-50%);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: var(--ink-soft);
  z-index: 20;
}

.cue-line {
  position: relative;
  width: 1px;
  height: 48px;
  background: rgba(30, 27, 24, 0.12);
  overflow: hidden;
}

.cue-line::after {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--rosewood);
  transform: translateY(-100%);
  animation: cueDrop 2.2s cubic-bezier(0.65, 0, 0.35, 1) infinite;
}

@keyframes cueDrop {
  0% {
    transform: translateY(-100%);
  }
  50% {
    transform: translateY(0);
  }
  100% {
    transform: translateY(100%);
  }
}

@media (max-width: 968px) {
  .hero-halo {
    right: 50%;
    width: 86vw;
    transform: translate(50%, -50%);
    opacity: 0.7;
  }

  .hero-scroll-cue {
    display: none;
  }

  /* Copy sits at the top, the bouquet shows beneath it */
  .hero-panel {
    align-items: flex-start;
    padding-top: 132px;
  }

  .hero-panel .hero-content {
    padding: 0;
    background: none;
    border: none;
    box-shadow: none;
    backdrop-filter: none;
  }

  .hero-halo {
    top: 72%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .halo-ring--inner,
  .cue-line::after {
    animation: none;
  }
}

.btn-learn-more {
  padding: 14px 38px;
  background: var(--ink);
  color: var(--ivory);
  border: none;
  border-radius: 999px;
  font-family: "Poppins", sans-serif;
  font-size: 14.5px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.btn-learn-more:hover {
  background: var(--rosewood);
  transform: translateY(-2px);
  box-shadow: 0 12px 28px rgba(184, 101, 88, 0.25);
}

/* ============================================================
   BOUQUET STORY (pinned)
============================================================= */
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

.eyebrow {
  display: inline-block;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--rosewood-dark);
}

.story {
  position: relative;
  z-index: 10;
  height: 100vh;
  height: 100svh;
}

.story-pin {
  position: relative;
  height: 100%;
  width: 100%;
}

.story-inner {
  position: relative;
  height: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 128px 6% 96px;
  display: grid;
  grid-template-columns: minmax(0, 460px) 1fr;
  grid-template-rows: auto 1fr;
}

.story-head {
  grid-column: 1 / -1;
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 24px;
}

.story-index {
  list-style: none;
  display: flex;
  gap: 32px;
}

.story-index li {
  display: flex;
  align-items: baseline;
  gap: 8px;
  color: var(--ink-soft);
  opacity: 0.45;
  transition: opacity 0.4s ease, color 0.4s ease;
}

.story-index li.is-active {
  opacity: 1;
  color: var(--ink);
}

.story-index-num {
  font-family: "Montserrat", sans-serif;
  font-size: 13px;
  font-weight: 700;
  color: var(--rosewood-dark);
}

.story-index-label {
  font-size: 13.5px;
  font-weight: 500;
}

.story-steps {
  grid-column: 1;
  position: relative;
  align-self: center;
  min-height: 360px;
}

.story-step {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  /* Steps overlap; only the one on screen takes clicks */
  pointer-events: none;
}

.story-step.is-current,
.story--static .story-step {
  pointer-events: auto;
}

.story-step-num {
  display: inline-block;
  margin-bottom: 16px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--rosewood-dark);
}

.story-step h3 {
  font-size: clamp(34px, 4.2vw, 58px);
  line-height: 1.04;
  margin-bottom: 20px;
  color: var(--ink);
}

.story-step--reveal h3 {
  font-size: clamp(30px, 3.4vw, 46px);
}

.story-step p {
  max-width: 420px;
  margin-bottom: 28px;
  font-size: 17px;
  line-height: 1.8;
  color: var(--ink-soft);
}

.swatch-group {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 14px;
}

.swatch {
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--swatch);
  border: 3px solid var(--ivory);
  box-shadow:
    0 0 0 1px rgba(30, 27, 24, 0.14),
    0 4px 12px rgba(30, 27, 24, 0.08);
  cursor: pointer;
  transition:
    transform 0.3s cubic-bezier(0.16, 1, 0.3, 1),
    box-shadow 0.3s ease;
}

.swatch--small {
  width: 26px;
  height: 26px;
  border-width: 2px;
}

.swatch:hover {
  transform: scale(1.1);
}

.swatch.is-active {
  box-shadow:
    0 0 0 2px var(--rosewood),
    0 6px 14px rgba(184, 101, 88, 0.25);
}

.swatch:focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 4px;
}

.story-features {
  list-style: none;
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 30px;
}

.story-features li {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border: 1px solid rgba(30, 27, 24, 0.1);
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.45);
  font-size: 13.5px;
  font-weight: 500;
}

.story-progress {
  position: absolute;
  left: max(6%, calc(50% - 600px + 6%));
  right: max(6%, calc(50% - 600px + 6%));
  bottom: 44px;
  height: 1px;
  background: rgba(30, 27, 24, 0.1);
  overflow: hidden;
}

.story-progress-fill {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, var(--rosewood), var(--gold));
  transform: scaleX(0);
  transform-origin: left;
}

/* Reduced motion: no pin, steps stack as regular content */
.story--static {
  height: auto;
}

.story--static .story-inner {
  height: auto;
  grid-template-rows: auto auto;
}

.story--static .story-steps {
  display: grid;
  gap: 64px;
  min-height: 0;
  margin-top: 48px;
}

.story--static .story-step {
  position: static;
}

.story--static .story-index,
.story--static .story-progress {
  display: none;
}

@media (max-width: 968px) {
  .story-inner {
    grid-template-columns: 1fr;
    grid-template-rows: auto 1fr auto;
    padding: 96px 5% 56px;
  }

  .story-index {
    gap: 14px;
  }

  .story-index-label {
    display: none;
  }

  .story-steps {
    grid-row: 3;
    align-self: end;
    min-height: 0;
  }

  .story-step {
    top: auto;
    bottom: 0;
    padding: 24px 22px;
    border: 1px solid rgba(30, 27, 24, 0.08);
    border-radius: 22px;
    background: rgba(244, 241, 236, 0.88);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
  }

  .story-step h3,
  .story-step--reveal h3 {
    font-size: 28px;
    margin-bottom: 12px;
  }

  .story-step p {
    font-size: 15px;
    line-height: 1.7;
    margin-bottom: 16px;
  }

  .story-features {
    margin-bottom: 18px;
  }

  .story-progress {
    bottom: 28px;
  }

  .story--static .story-step {
    position: static;
  }
}

/* ============================================================
   PLANS (pinned horizontal track)
============================================================= */
.plans-section {
  position: relative;
  z-index: 10;
  min-height: 100vh;
  min-height: 100svh;
  display: flex;
  align-items: center;
  overflow: hidden;
}

.plans-inner {
  width: 100%;
  display: grid;
  grid-template-columns: minmax(280px, 360px) minmax(0, 1fr);
  gap: 56px;
  align-items: center;
  padding: 120px 0 80px max(6%, calc(50% - 600px + 6%));
}

.plans-head h2 {
  margin: 16px 0 18px;
  font-size: clamp(32px, 3.6vw, 52px);
  line-height: 1.05;
}

.plans-head p {
  margin-bottom: 24px;
  color: var(--ink-soft);
  font-size: 16px;
  line-height: 1.75;
}

.plans-compare {
  display: inline-flex;
  gap: 8px;
  color: var(--rosewood-dark);
  font-size: 14.5px;
  font-weight: 600;
  text-decoration: none;
  transition: gap 0.3s ease;
}

.plans-compare:hover {
  gap: 12px;
}

.plans-progress {
  width: 100%;
  max-width: 220px;
  height: 2px;
  margin-top: 40px;
  background: rgba(30, 27, 24, 0.1);
  overflow: hidden;
}

.plans-progress-fill {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, var(--rosewood), var(--gold));
  transform: scaleX(0);
  transform-origin: left;
}

.plans-viewport {
  overflow: hidden;
  /* Room for card shadows; cards fade in/out at the left edge */
  padding: 56px 0 64px 24px;
  margin: -56px 0 -64px -24px;
  -webkit-mask-image: linear-gradient(90deg, transparent 0, #000 56px);
  mask-image: linear-gradient(90deg, transparent 0, #000 56px);
}

.plans-track {
  display: flex;
  gap: 24px;
  width: max-content;
  padding-right: 6vw;
  will-change: transform;
}

.plans-card {
  width: clamp(300px, 26vw, 360px);
  flex-shrink: 0;
}

.plans-notice {
  grid-column: 1 / -1;
  max-width: 720px;
  padding: 12px 16px;
  border: 1px solid #eacb95;
  border-radius: 12px;
  background: #fff5df;
  color: #5b3a1e;
  font-size: 14px;
}

.plans-notice a {
  color: var(--rosewood-dark);
  font-weight: 600;
}

/* Mobile and reduced motion: native horizontal scroll with snap, no pin */
.plans-section--static .plans-viewport {
  overflow-x: auto;
  scroll-snap-type: x mandatory;
}

.plans-section--static .plans-card {
  scroll-snap-align: start;
}

@media (max-width: 968px) {
  .plans-section {
    min-height: auto;
  }

  .plans-inner {
    grid-template-columns: 1fr;
    gap: 28px;
    padding: 96px 0 72px 5%;
  }

  .plans-head {
    padding-right: 5%;
  }

  .plans-progress {
    display: none;
  }

  .plans-viewport {
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    padding: 20px 0 32px;
    margin: 0;
    -webkit-mask-image: none;
    mask-image: none;
  }

  .plans-viewport::-webkit-scrollbar {
    display: none;
  }

  .plans-card {
    width: min(82vw, 340px);
    scroll-snap-align: start;
  }

  .plans-notice {
    margin-right: 5%;
  }
}

/* Stats Panel */
.stats-panel {
  min-height: auto;
  padding-block: 140px;
}

.stats-panel .panel-container {
  grid-template-columns: 1fr;
}

@media (min-width: 969px) {
  .stats-panel .stats-stacked-grid {
    grid-template-columns: repeat(4, 1fr);
  }

  .stats-panel .stat-card-item,
  .stats-panel .stat-card-item:nth-child(odd) {
    border-left: 1px solid var(--line);
    padding-left: 32px;
  }

  .stats-panel .stat-card-item:first-child {
    border-left: none;
    padding-left: 0;
  }
}

.stats-header-desc {
  color: var(--ink-soft);
  font-size: 16.5px;
  margin-bottom: 44px;
  max-width: 460px;
}

.stats-stacked-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0;
}

.stat-card-item {
  position: relative;
  background: none;
  backdrop-filter: none;
  border: none;
  border-left: 1px solid var(--line);
  border-radius: 0;
  padding: 12px 0 20px 32px;
  text-align: left;
  transition: none;
}

.stat-card-item:nth-child(odd) {
  border-left: none;
  padding-left: 0;
}

.stat-card-item:hover {
  transform: none;
  box-shadow: none;
  background: none;
}

.stat-icon {
  font-size: 20px;
  margin-bottom: 14px;
  opacity: 0.55;
}

.stat-number {
  font-size: 44px;
  margin-bottom: 6px;
  background: linear-gradient(120deg, var(--ink) 45%, var(--rosewood) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  display: inline-flex;
  align-items: center;
  gap: 10px;
}

.live-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #6f9c5f;
  -webkit-text-fill-color: #6f9c5f;
  flex-shrink: 0;
  box-shadow: 0 0 0 0 rgba(111, 156, 95, 0.55);
  animation: liveDotPulse 2.2s ease-out infinite;
}

@keyframes liveDotPulse {
  0% {
    box-shadow: 0 0 0 0 rgba(111, 156, 95, 0.45);
  }
  70% {
    box-shadow: 0 0 0 9px rgba(111, 156, 95, 0);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(111, 156, 95, 0);
  }
}

@media (prefers-reduced-motion: reduce) {
  .live-dot {
    animation: none;
  }
}

.stat-label {
  color: var(--ink-soft);
  font-size: 14px;
  letter-spacing: 0.02em;
  font-weight: 500;
  position: relative;
  padding-bottom: 14px;
}

.stat-label::after {
  content: "";
  position: absolute;
  left: 0;
  bottom: 0;
  height: 2px;
  width: 100%;
  background: var(--rosewood);
  transform: scaleX(0);
  transform-origin: left;
  transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}

.stat-card-item:hover .stat-label::after {
  transform: scaleX(1);
}

/* Blog Panel */
.blog-panel h2 {
  font-size: clamp(30px, 3.5vw, 40px);
  margin-bottom: 14px;
}

.blog-header-desc {
  color: var(--ink-soft);
  font-size: 16.5px;
  margin-bottom: 48px;
}

.blog-stacked-grid {
  display: flex;
  flex-direction: column;
  gap: 28px;
}

.blog-card-item {
  background: none;
  backdrop-filter: none;
  border-radius: 4px;
  overflow: hidden;
  border: none;
  border-bottom: 1px solid var(--line);
  display: flex;
  padding-bottom: 28px;
  transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.blog-card-item:last-child {
  border-bottom: none;
}

.blog-card-item:hover {
  transform: translateX(6px);
  border-color: var(--line);
  box-shadow: none;
  background: none;
}

.blog-image {
  width: 180px;
  height: 130px;
  background: linear-gradient(150deg, var(--ivory-deep), #e9ddcd);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #b7afa6;
  font-size: 12px;
  text-align: center;
  padding: 20px;
  flex-shrink: 0;
  border-right: none;
  transition: opacity 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.blog-card-item:hover .blog-image {
  opacity: 0.82;
}

.blog-content {
  padding: 28px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.blog-content h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 12px;
  line-height: 1.35;
}

.blog-link {
  color: var(--rosewood-dark);
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: gap 0.3s;
}

.blog-link:hover {
  gap: 10px;
}

/* ============================================================
   ABOUT US
============================================================= */
.about-section {
  position: relative;
  z-index: 10;
  padding: 170px 6%;
  background: linear-gradient(
    180deg,
    transparent,
    rgba(235, 229, 221, 0.75) 18%,
    rgba(235, 229, 221, 0.75) 82%,
    transparent
  );
}

.about-inner {
  max-width: 1200px;
  margin: 0 auto;
}

.about-head h2 {
  max-width: 760px;
  margin: 16px 0 80px;
  font-size: clamp(32px, 4vw, 56px);
  line-height: 1.06;
}

.about-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 72px;
}

.about-card {
  padding-top: 28px;
  border-top: 1px solid rgba(30, 27, 24, 0.14);
}

.about-num {
  font-family: "Montserrat", sans-serif;
  font-size: 13px;
  font-weight: 700;
  color: var(--rosewood-dark);
}

.about-card h3 {
  margin: 12px 0 20px;
  font-size: 26px;
}

.about-text {
  font-family: "Montserrat", sans-serif;
  font-size: clamp(18px, 1.55vw, 22px);
  font-weight: 500;
  line-height: 1.62;
  letter-spacing: -0.01em;
  color: var(--ink);
}

.about-rule {
  height: 1px;
  margin-top: 96px;
  background: linear-gradient(90deg, var(--gold), rgba(212, 178, 122, 0));
  transform-origin: left;
}

@media (max-width: 968px) {
  .about-section {
    padding: 110px 5%;
  }

  .about-head h2 {
    margin-bottom: 52px;
  }

  .about-grid {
    grid-template-columns: 1fr;
    gap: 52px;
  }

  .about-rule {
    margin-top: 64px;
  }
}

/* CTA Panel */
.cta-panel {
  display: flex;
  justify-content: center;
  /* Copy sits low; the bouquet returns above it */
  align-items: flex-end;
  text-align: center;
  padding: 160px 6% 14vh;
}

.cta-container {
  display: flex;
  justify-content: center;
}

.cta-content {
  max-width: 680px;
}

.cta-panel h2 {
  font-size: clamp(34px, 4.6vw, 54px);
  margin-bottom: 40px;
  line-height: 1.15;
  color: var(--ink);
}

.btn-cta {
  padding: 18px 56px;
  background: var(--ink);
  color: var(--ivory);
  border: none;
  border-radius: 999px;
  font-family: "Poppins", sans-serif;
  font-size: 15.5px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
  display: inline-block;
  text-decoration: none;
}

.btn-cta:hover {
  background: var(--rosewood);
  transform: translateY(-3px);
  box-shadow: 0 20px 40px rgba(184, 101, 88, 0.3);
}

/* Petal drifts (fixed layer decorative) */
.drift-petal {
  position: absolute;
  width: 14px;
  height: 20px;
  border-radius: 60% 0 60% 0;
  background: linear-gradient(135deg, #f3c8bd, #e4988f);
  opacity: 0.7;
  z-index: 3;
  animation: petalDrift 11s ease-in-out infinite;
  pointer-events: none;
}

.petal-a {
  top: 12%;
  left: 10%;
  animation-delay: 0s;
}

.petal-b {
  top: 55%;
  right: 10%;
  animation-delay: 2.4s;
  transform: rotate(30deg);
}

.petal-c {
  top: 25%;
  right: 22%;
  animation-delay: 4.8s;
  transform: rotate(-20deg);
}

.petal-d {
  top: 70%;
  left: 20%;
  animation-delay: 6.6s;
  transform: rotate(12deg);
}

.petal-e {
  top: 40%;
  left: 65%;
  animation-delay: 8.2s;
  transform: rotate(-8deg);
}

@keyframes petalDrift {
  0% {
    transform: translate(0, 0) rotate(0deg);
    opacity: 0.15;
  }
  20% {
    opacity: 0.6;
  }
  50% {
    transform: translate(-12px, 26vh) rotate(20deg);
  }
  80% {
    opacity: 0.35;
  }
  100% {
    transform: translate(6px, 52vh) rotate(45deg);
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .drift-petal {
    animation: none;
    opacity: 0.3;
  }
}

/* Footer */
.footer {
  position: relative;
  z-index: 15;
  background: var(--espresso);
  color: var(--ivory);
  padding: 90px 6% 36px;
}

.footer-content {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 60px;
  max-width: 1200px;
  margin: 0 auto 54px;
}

.footer-brand {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.footer-brand .logo {
  color: var(--ivory);
  font-size: 23px;
}

.footer-brand p {
  color: rgba(250, 248, 245, 0.55);
  font-size: 14.5px;
  line-height: 1.75;
  max-width: 320px;
}

.footer-section h4 {
  font-family: "Poppins", sans-serif;
  font-size: 13.5px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: 22px;
  font-weight: 600;
  color: rgba(250, 248, 245, 0.9);
}

.footer-section ul {
  list-style: none;
}

.footer-section ul li {
  margin-bottom: 14px;
}

.footer-section ul li a {
  color: rgba(250, 248, 245, 0.6);
  text-decoration: none;
  font-size: 14.5px;
  transition: color 0.3s;
}

.footer-section ul li a:hover {
  color: var(--ivory);
}

.footer-bottom {
  text-align: center;
  padding-top: 36px;
  border-top: 1px solid rgba(250, 248, 245, 0.1);
  color: rgba(250, 248, 245, 0.45);
  font-size: 13.5px;
}

.social-links {
  display: flex;
  gap: 16px;
  margin-top: 8px;
}

.social-link {
  width: 38px;
  height: 38px;
  background: rgba(250, 248, 245, 0.08);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ivory);
  text-decoration: none;
  transition: all 0.3s;
}

.social-link:hover {
  background: var(--rosewood);
  transform: translateY(-3px);
}

/* ============================================================
   RESPONSIVE DESIGN STYLES
============================================================= */
@media (max-width: 968px) {
  /* At top: still full width, just less padding */
  .navbar {
    padding: 1.2rem 5%;
  }

  /* Scrolled: floating pill, narrower margin */
  .navbar--scrolled {
    top: 14px;
    width: calc(100% - 32px);
    padding: 0.65rem 1.4rem;
  }

  .nav-toggle {
    display: inline-block;
  }

  .nav-links {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    right: 0;
    flex-direction: column;
    align-items: stretch;
    gap: 0;
    padding: 10px 22px;
    border: 1px solid rgba(30, 27, 24, 0.08);
    border-radius: 22px;
    background: rgba(250, 248, 245, 0.97);
    box-shadow: 0 18px 40px rgba(30, 27, 24, 0.12);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition:
      opacity 0.3s ease,
      transform 0.3s ease,
      visibility 0.3s;
  }

  .navbar--menu-open .nav-links {
    opacity: 1;
    visibility: visible;
    transform: none;
  }

  .nav-links a {
    padding: 14px 0;
    font-size: 15.5px;
    border-bottom: 1px solid rgba(30, 27, 24, 0.06);
  }

  .nav-links a:last-child {
    border-bottom: none;
  }

  .nav-links a::after {
    display: none;
  }

  .panel-section {
    padding: 100px 5%;
  }

  .panel-container {
    grid-template-columns: 1fr;
    gap: 40px;
  }

  .panel-content {
    max-width: 100%;
    text-align: center;
    padding: 44px 32px;
    background: rgba(250, 248, 245, 0.88);
    border-radius: 28px;
    backdrop-filter: blur(16px);
    border: 1px solid var(--line);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.015);
  }

  .hero-panel {
    padding-top: 140px;
  }

  .hero-title {
    font-size: 38px;
  }

  .hero-desc {
    max-width: 100%;
  }

  .panel-visual {
    display: none;
  }

  .blog-stacked-grid {
    gap: 20px;
  }

  .blog-card-item {
    background: rgba(250, 248, 245, 0.4);
    padding: 20px;
    flex-direction: column;
  }

  .blog-image {
    width: 100%;
    border-right: none;
    border-bottom: 1px solid var(--line);
    padding: 15px;
  }

  .blog-content {
    padding: 20px;
  }

  .stats-stacked-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .stat-card-item {
    padding: 12px 0 16px 24px;
  }

  .stat-number {
    font-size: 34px;
  }

  .footer-content {
    grid-template-columns: 1fr;
    gap: 40px;
  }
}

@media (max-width: 640px) {
  /* At top: full width, compact padding */
  .navbar {
    padding: 1rem 4%;
  }

  /* Scrolled: pill with very slight rounding on small screens */
  .navbar--scrolled {
    top: 10px;
    width: calc(100% - 24px);
    padding: 0.55rem 1.1rem;
    border-radius: 20px;
  }

  .panel-section {
    padding: 80px 4%;
  }

  .panel-content {
    padding: 32px 22px;
  }

  .hero-title {
    font-size: 32px;
  }

  .hero-desc {
    font-size: 16px;
  }

  .btn-hero {
    padding: 14px 34px;
    width: 100%;
  }

  .stats-stacked-grid {
    grid-template-columns: 1fr;
  }

  .nav-buttons {
    gap: 10px;
  }

  .btn-login,
  .btn-register {
    padding: 8px 18px;
    font-size: 13px;
  }

  /* Login moves into the menu; Register stays in the bar */
  .nav-buttons .btn-login {
    display: none;
  }

  .nav-menu-login {
    display: block;
  }

  .logo {
    font-size: 19px;
    gap: 8px;
  }

  .logo img {
    width: 38px;
    height: 38px;
  }
}
</style>
