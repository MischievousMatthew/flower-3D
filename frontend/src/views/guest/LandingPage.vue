<template>
  <div class="landing-page bc-theme">
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

    <!-- Persistent 3D bouquet: fixed, full-viewport, driven by scroll -->
    <div ref="flowerStage" class="flower-stage-fixed" aria-hidden="true">
      <canvas ref="flowerCanvas" class="flower-canvas"></canvas>
    </div>

    <!-- Cinematic blackout: an espresso iris that closes the page into the footer -->
    <div ref="blackoutLayer" class="blackout" aria-hidden="true"></div>

    <!-- Navigation: hides while scrolling down, returns on scroll up -->
    <nav
      class="navbar bc-navbar"
      :class="{
        'navbar--scrolled': navScrolled,
        'bc-navbar--scrolled': navScrolled,
        'navbar--hidden': navHidden,
        'navbar--menu-open': mobileMenuOpen,
        'bc-navbar--menu-open': mobileMenuOpen,
      }"
      aria-label="Main navigation"
    >
      <router-link to="/" class="logo bc-logo" @click="closeMobileMenu">
        <img
          src="/bloomcraft-blankBg.png"
          alt="Bloomcraft Logo"
          width="44"
          height="44"
        />
        <span class="bc-logo-text">BloomCraft</span>
      </router-link>
      <div id="primary-nav" class="nav-links bc-nav-links">
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
          class="bc-nav-menu-only"
          @click="closeMobileMenu"
          >Login</router-link
        >
      </div>
      <div class="nav-buttons">
        <template v-if="!isAuthenticated">
          <router-link to="/guest/login" class="btn-login bc-nav-login">Login</router-link>
          <router-link to="/guest/register" class="btn-register bc-nav-register"
            >Register</router-link
          >
        </template>
        <button
          type="button"
          class="bc-nav-toggle"
          :aria-expanded="mobileMenuOpen"
          aria-controls="primary-nav"
          :aria-label="mobileMenuOpen ? 'Close menu' : 'Open menu'"
          @click="mobileMenuOpen = !mobileMenuOpen"
        >
          <span class="bc-nav-toggle-bar" aria-hidden="true"></span>
          <span class="bc-nav-toggle-bar" aria-hidden="true"></span>
        </button>
      </div>
    </nav>

    <main id="main-content" class="scroll-story-container" tabindex="-1">
      <!-- Hero -->
      <section class="hero" id="top" ref="heroSection">
        <div class="hero-halo" aria-hidden="true">
          <span class="halo-ring halo-ring--outer"></span>
          <span class="halo-ring halo-ring--inner"></span>
        </div>
        <div class="bc-container">
          <div class="hero-content">
            <p class="bc-label hero-reveal">Bespoke florals, built by you</p>
            <h1 class="hero-title" aria-label="Create your perfect bouquet">
              <span class="line" aria-hidden="true"
                ><span class="line-inner">Create your</span></span
              >
              <span class="line" aria-hidden="true"
                ><span class="line-inner">perfect <em>bouquet</em></span></span
              >
            </h1>
            <p class="hero-desc hero-reveal">
              Where vendors meet creativity. Design custom flower arrangements
              in 3D or let our AI suggest the perfect bloom for every occasion.
            </p>
            <div class="hero-actions hero-reveal">
              <router-link to="/guest/register" class="bc-btn bc-btn--dark">
                Get Started
                <span class="bc-btn-arrow" aria-hidden="true">→</span>
              </router-link>
              <a
                href="#how-it-works"
                class="bc-link"
                @click.prevent="navigateTo('how-it-works')"
                >How it works</a
              >
            </div>
          </div>
        </div>
        <div class="bc-container hero-foot hero-scroll-cue hero-reveal" aria-hidden="true">
          <span class="hero-foot-cue"><span class="cue-line"></span>Scroll to explore</span>
          <span class="hero-foot-note">Hand-tied by local florists · Designed by you</span>
        </div>
      </section>

      <!-- Bouquet Story: pinned, scroll-driven tour of the real 3D bouquet -->
      <section
        id="bouquet-story"
        ref="storySection"
        class="story"
        :class="{ 'story--static': storyStatic }"
        aria-labelledby="story-heading"
      >
        <div class="story-pin">
          <div class="bc-container story-inner">
            <header class="story-head">
              <p class="bc-label">The 3D bouquet studio</p>
              <h2 id="story-heading" class="bc-sr-only">
                Build a bouquet in three steps
              </h2>
              <ol class="story-index" aria-hidden="true">
                <li
                  v-for="(step, i) in storySteps"
                  :key="step.key"
                  :class="{ 'is-active': activeStep === i }"
                >
                  <span class="story-index-num">{{ ["i", "ii", "iii"][i] }}</span>
                  <span class="story-index-label">{{ step.label }}</span>
                </li>
              </ol>
            </header>

            <div class="story-steps" @focusin="revealFocusedStep">
              <article
                id="features"
                class="story-step"
                data-step="0"
                :class="{ 'is-current': activeStep === 0 }"
              >
                <p class="story-step-label">Blooms</p>
                <h3>Choose your <em>blooms</em></h3>
                <p>
                  Pick real flowers from local vendors and arrange them in real
                  time. Rotate, adjust and see every petal before you order.
                </p>
                <div class="bloom-picker" role="group" aria-label="Bloom variety">
                  <button
                    v-for="variety in bloomVarieties"
                    :key="variety.key"
                    type="button"
                    class="bloom-option"
                    :class="{ 'is-active': bloomVariety === variety.key }"
                    :aria-pressed="bloomVariety === variety.key"
                    @click="pickBloom(variety.key)"
                  >
                    {{ variety.label }}
                  </button>
                </div>
              </article>

              <article
                class="story-step"
                data-step="1"
                :class="{ 'is-current': activeStep === 1 }"
              >
                <p class="story-step-label">Wrapper</p>
                <h3>Wrap it <em>your way</em></h3>
                <p>
                  Switch paper and inner layers to match the occasion, from
                  kraft and linen to blush and plum.
                </p>
                <div class="swatch-row">
                  <span class="swatch-caption">Paper</span>
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
                </div>
                <div class="swatch-row">
                  <span class="swatch-caption">Inner layer</span>
                  <div
                    class="swatch-group"
                    role="group"
                    aria-label="Inner layer colour"
                  >
                    <button
                      v-for="swatch in innerSwatches"
                      :key="swatch.color"
                      type="button"
                      class="swatch"
                      :class="{ 'is-active': innerColor === swatch.color }"
                      :style="{ '--swatch': swatch.color }"
                      :aria-label="`${swatch.name} inner layer`"
                      :aria-pressed="innerColor === swatch.color"
                      @click="pickInner(swatch.color)"
                    ></button>
                  </div>
                </div>
              </article>

              <article
                class="story-step"
                data-step="2"
                :class="{ 'is-current': activeStep === 2 }"
              >
                <p class="story-step-label">Fillers</p>
                <h3>Finish with <em>fillers</em></h3>
                <p>
                  Eucalyptus, baby's breath and greenery bring it together. Not
                  sure where to start? Our AI concierge suggests the perfect
                  combination for the occasion, season and recipient.
                </p>
              </article>

              <article
                class="story-step story-step--reveal"
                data-step="3"
                :class="{ 'is-current': activeStep === 3 }"
              >
                <p class="story-step-label">Your bouquet</p>
                <h3>Every arrangement is unique, <em>just like your story.</em></h3>
                <ul class="story-features">
                  <li v-for="feature in features" :key="feature.id">
                    {{ feature.title }}
                  </li>
                </ul>
                <button
                  class="bc-btn bc-btn--dark btn-learn-more"
                  @click="handleLearnMore('3d-designer')"
                >
                  Explore 3D Designer
                  <span class="bc-btn-arrow" aria-hidden="true">→</span>
                </button>
              </article>
            </div>
          </div>
          <div class="bc-container story-progress-wrap" aria-hidden="true">
            <div class="story-progress">
              <span ref="storyProgressFill" class="story-progress-fill"></span>
            </div>
          </div>
        </div>
      </section>

      <!-- How it works -->
      <section
        id="how-it-works"
        ref="processSection"
        class="process"
        aria-labelledby="process-heading"
      >
        <div class="bc-container">
          <div class="bc-section-head">
            <p class="bc-label">How it works</p>
            <h2 id="process-heading">
              From a local florist's bench <em>to your door</em>
            </h2>
          </div>
          <ol class="process-list">
            <li
              v-for="(step, i) in processSteps"
              :key="step.title"
              class="process-step"
            >
              <span class="process-num" aria-hidden="true"
                >{{ ["i", "ii", "iii"][i] }}.</span
              >
              <h3>{{ step.title }}</h3>
              <p>{{ step.text }}</p>
            </li>
          </ol>
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
            <p class="bc-label">For flower businesses</p>
            <h2 id="plans-heading">Plans that grow <em>with your shop</em></h2>
            <p>
              Start with the essentials, then grow into a connected ERP
              workspace when your operations need more.
            </p>
            <router-link to="/pricing" class="bc-link"
              >Compare all features</router-link
            >
            <div class="plans-progress" aria-hidden="true">
              <span ref="plansProgressFill" class="plans-progress-fill"></span>
            </div>
          </div>
          <div ref="plansViewport" class="plans-viewport">
            <div
              ref="plansTrack"
              class="plans-track"
              @focusin="revealFocusedPlan"
            >
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

      <!-- Journal -->
      <section
        id="blog"
        ref="blogSection"
        class="journal"
        aria-labelledby="journal-heading"
      >
        <div class="bc-container">
          <div class="bc-section-head bc-section-head--split">
            <div>
              <p class="bc-label">The BloomCraft Journal</p>
              <h2 id="journal-heading">Notes from the <em>flower world</em></h2>
            </div>
            <p class="bc-section-intro">
              Tips, trends, and stories from the world of flowers
            </p>
          </div>
          <ul class="journal-list">
            <li v-for="(post, i) in blogPosts" :key="post.id">
              <a :href="post.slug ? `/journal/${post.slug}` : '/journal'" class="journal-row" @click.prevent="readBlog(post)">
                <span class="journal-index">{{
                  String(i + 1).padStart(2, "0")
                }}</span>
                <span class="journal-category">{{ post.category }}</span>
                <span class="journal-title">{{ post.title }}</span>
                <span class="journal-arrow" aria-hidden="true">
                  <svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                </span>
              </a>
            </li>
          </ul>
        </div>
      </section>

      <!-- About Us: Vision and Mission -->
      <section
        id="about"
        ref="aboutSection"
        class="about-section"
        aria-labelledby="about-heading"
      >
        <div class="bc-container about-inner">
          <header class="bc-section-head about-head">
            <p class="bc-label">About us</p>
            <h2 id="about-heading">The purpose behind <em>every bouquet</em></h2>
          </header>
          <div class="about-grid">
            <article
              v-for="statement in aboutStatements"
              :key="statement.title"
              class="about-card"
            >
              <h3 class="about-title">{{ statement.title }}</h3>
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

      <!-- Closing call to action -->
      <section class="cta-panel" ref="ctaSection">
        <div class="bc-container cta-content">
          <h2>Ready to create something <em>beautiful</em>?</h2>
          <router-link to="/guest/register" class="bc-btn bc-btn--dark btn-cta">
            Start Designing Now
            <span class="bc-btn-arrow" aria-hidden="true">→</span>
          </router-link>
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
      <div class="bc-container">
        <div class="footer-content">
          <div class="footer-brand">
            <div class="footer-logo">
              <img
                src="/bloomcraft-darkmode-removebg.png"
                alt="Bloomcraft Logo"
                width="48"
                height="48"
              />
              <span>BloomCraft</span>
            </div>
            <p>
              {{ footerAbout }}
            </p>
            <ul class="social-links">
              <li v-for="social in socialLinks" :key="social.name">
                <a
                  :href="social.url"
                  class="social-link"
                  target="_blank"
                  rel="noopener"
                  >{{ social.name }}</a
                >
              </li>
            </ul>
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
        <p class="footer-wordmark" aria-hidden="true">BloomCraft</p>
        <div class="footer-bottom">
          <p>Copyright © {{ currentYear }} BloomCraft. All rights reserved.</p>
        </div>
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
import { BLOOM_VARIETIES } from "../../composables/landingFlowers";
import {
  createLandingBouquet,
  PAPER_PRESETS,
  RIBBON_PRESETS,
} from "../../composables/useLandingBouquet";
import "../../styles/bloomcraft.css";
import { useSiteContent } from "../../composables/useSiteContent";
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
const processSection = ref(null);
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

// Bloom varieties (sculpted heads in landingFlowers.js). Scrolling through
// the Blooms step cycles them until the visitor picks one.
const bloomVarieties = BLOOM_VARIETIES;
const bloomVariety = ref("signature");
let userPickedBloom = false;

function pickBloom(key) {
  userPickedBloom = true;
  bloomVariety.value = key;
  bouquet?.setBloom(key);
}

function cycleBloom(index) {
  if (userPickedBloom) return;
  const key = BLOOM_VARIETIES[index]?.key;
  if (!key || key === bloomVariety.value) return;
  bloomVariety.value = key;
  bouquet?.setBloom(key);
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

// How it works (replaces the old placeholder marketplace stats).
const processSteps = [
  {
    title: "Find a local florist",
    text: "Browse storefronts from flower shops near you and choose who will craft your arrangement.",
  },
  {
    title: "Design it in 3D",
    text: "Pick blooms, wrapping and fillers in the 3D studio, or let our AI concierge suggest a combination for the occasion.",
  },
  {
    title: "Hand-tied and delivered",
    text: "Your florist prepares the bouquet you designed and delivers it fresh to the door.",
  },
];

// Website content from Admin → Website (falls back to the original copy)
const { site } = useSiteContent();
const PLACEHOLDER_POSTS = [
  { id: 1, title: "Seasonal Flower Guide for Spring", category: "Seasonal guide" },
  { id: 2, title: "How to Care for Your Custom Bouquet", category: "Flower care" },
  { id: 3, title: "Meet Our Featured Vendor: Petal & Co", category: "Vendor stories" },
];
const blogPosts = computed(() =>
  site.articles.length
    ? site.articles.map((a) => ({ id: a.id, slug: a.slug, title: a.title, category: a.category_label }))
    : PLACEHOLDER_POSTS,
);
const footerAbout = computed(
  () =>
    site.settings.about ||
    "Your marketplace for custom flower arrangements. Connect with local vendors and design the perfect bouquet.",
);

const SOCIAL_NAMES = { facebook: "Facebook", instagram: "Instagram", tiktok: "TikTok", x: "X" };
const socialLinks = computed(() =>
  Object.entries(site.settings.socials ?? {})
    .filter(([, url]) => url)
    .map(([key, url]) => ({ name: SOCIAL_NAMES[key] ?? key, url })),
);

const companyLinks = computed(() => [
  { name: "About Us", url: "#about" },
  { name: "Journal", url: "/journal" },
  ...(site.settings.contact_email ? [{ name: "Contact", url: `mailto:${site.settings.contact_email}` }] : []),
]);

const supportLinks = computed(() => [
  { name: "Shop", url: "/shop" },
  ...(site.settings.contact_email ? [{ name: site.settings.contact_email, url: `mailto:${site.settings.contact_email}` }] : []),
  ...(site.settings.contact_phone ? [{ name: site.settings.contact_phone, url: `tel:${site.settings.contact_phone.replace(/s+/g, "")}` }] : []),
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

const readBlog = (post) => router.push(post.slug ? `/journal/${post.slug}` : "/journal");

const handleFooterLink = (url) => {
  if (url === "#about") {
    scrollToSection("about");
    return;
  }
  if (/^(mailto|tel):/.test(url)) {
    window.location.href = url;
    return;
  }
  if (url.startsWith("/")) router.push(url);
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
const STORY_LENGTH = 11; // timeline units
const STEP_TIMES = [0.2, 4.2, 6.8, 9.2]; // when each story step enters
const STORY_ANCHORS = { features: 0.12 }; // nav targets inside the pinned story (progress)
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
  const heroSy = isMobile ? -0.56 : 0;
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
          end: isMobile ? "+=380%" : "+=520%",
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

      // 01 Blooms: zoom into the flowers, soften everything else, and
      // cycle through the bloom varieties.
      const bloomProxy = { i: 0 };
      // immediateRender: false keeps the hero framing until the story starts.
      tl.fromTo(bouquet.view, { ...overview }, { ...flowers, duration: 1.4, immediateRender: false }, 0.4);
      tl.to(bouquet.focus, { wrapper: 0.25, fillers: 0.35, duration: 1 }, 0.4);
      tl.to(
        bloomProxy,
        {
          i: BLOOM_VARIETIES.length - 0.01,
          duration: 2.8,
          ease: "none",
          onUpdate: () => cycleBloom(Math.floor(bloomProxy.i)),
        },
        0.8,
      );

      // 02 Wrapper: pull back and orbit to the paper; cycle swatches.
      tl.to(bouquet.view, { ...wrapper, duration: 1.4 }, 4);
      tl.to(rig.rotation, { y: 0.55, duration: 1.6 }, 4);
      tl.to(bouquet.focus, { flowers: 0.35, wrapper: 1, fillers: 0.3, duration: 1 }, 4);
      tl.to(
        paperProxy,
        {
          i: PAPER_PRESETS.length - 0.01,
          duration: 2,
          ease: "none",
          onUpdate: () => cyclePaper(Math.floor(paperProxy.i)),
        },
        4.6,
      );

      // 03 Fillers: zoom onto leaves and small plants.
      tl.to(bouquet.view, { ...fillers, duration: 1.4 }, 6.6);
      tl.to(rig.rotation, { y: -0.45, duration: 1.6 }, 6.6);
      tl.to(bouquet.focus, { flowers: 0.35, wrapper: 0.25, fillers: 1, duration: 1 }, 6.6);

      // Reveal: full bouquet, one slow turn.
      tl.to(bouquet.view, { ...finale, duration: 1.6 }, 9);
      tl.to(bouquet.focus, { flowers: 1, wrapper: 1, fillers: 1, duration: 1 }, 9);
      tl.to(rig.rotation, { y: Math.PI * 2, duration: 2, ease: "power1.inOut" }, 9);
      tl.set({}, {}, STORY_LENGTH);

      storyTrigger = tl.scrollTrigger;
    }

    // ---- After the story the bouquet steps aside for content ----
    const stats = processSection.value;
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
      tl.to(".navbar .bc-nav-toggle-bar", { backgroundColor: "#faf8f5", ease: "none" }, 0);
      tl.fromTo(
        footer.querySelectorAll(".footer-brand, .footer-section, .footer-wordmark, .footer-bottom"),
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
/* Fonts, tokens, type, labels, buttons and the navbar base live in
   src/styles/bloomcraft.css (shared with the customer pages). */

/* ============================================================
   DESIGN TOKENS
============================================================= */
.landing-page {
  position: relative;
  background: var(--ivory);
  overflow-x: hidden;
}

/* Reset in a cascade layer: it zeroes browser defaults but never overrides
   real styles (incl. the shared bloomcraft.css rules), whatever the CSS order. */
@layer bc-reset {
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }
}

/* Same body reset the pricing page applies; removes the UA 8px margin. */
:global(body) {
  margin: 0;
}

/* ============================================================
   TYPOGRAPHY
============================================================= */
/* One quiet label style for every section */
/* ============================================================
   BUTTONS + LINKS
============================================================= */
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
  font-weight: 500;
  text-decoration: none;
  transform: translateY(-160%);
  transition: transform 0.25s ease;
}

.skip-link:focus {
  transform: none;
  outline: 2px solid var(--rosewood);
  outline-offset: 3px;
}

.scroll-story-container {
  position: relative;
  z-index: 10;
  width: 100%;
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
  background: var(--rosewood);
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
  background: rgba(250, 248, 244, 0.92);
  box-shadow: 0 10px 28px rgba(30, 27, 24, 0.12);
  color: var(--ink);
  cursor: pointer;
  opacity: 0;
  visibility: hidden;
  transform: translateY(12px);
  transition:
    opacity 0.35s ease,
    transform 0.35s var(--ease-out),
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
  stroke-width: 1.5;
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
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition: transform 0.3s ease;
}

.back-to-top:hover .btt-arrow {
  transform: translateY(-2px);
}

/* ============================================================
   AMBIENT BACKGROUND + 3D LAYER
============================================================= */
.bg-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(90px);
  pointer-events: none;
  z-index: 0;
}

.blob-rose {
  top: -8%;
  right: -5%;
  width: 48vw;
  height: 48vw;
  background: radial-gradient(circle, rgba(184, 101, 88, 0.16), transparent 70%);
}

.blob-sage {
  top: 50%;
  left: -10%;
  width: 42vw;
  height: 42vw;
  background: radial-gradient(circle, rgba(156, 181, 145, 0.12), transparent 70%);
}

.grain-overlay {
  position: fixed;
  inset: 0;
  z-index: 2000;
  pointer-events: none;
  opacity: 0.035;
  mix-blend-mode: overlay;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
}

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

/* Blackout iris: above the bouquet, below the content */
.blackout {
  position: fixed;
  inset: 0;
  z-index: 8;
  background: var(--espresso);
  clip-path: circle(0% at 50% 62%);
  pointer-events: none;
}

/* ============================================================
   NAVIGATION — full width at top, floating pill when scrolled
============================================================= */
.navbar--hidden {
  transform: translate(-50%, calc(-100% - 32px));
}

.navbar--hidden:focus-within {
  transform: translateX(-50%);
}

.nav-buttons {
  display: flex;
  align-items: center;
  gap: 20px;
}

/* ============================================================
   HERO
============================================================= */
.hero {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: center;
  min-height: 100vh;
  min-height: 100svh;
  padding: 150px 0 140px;
}

.hero-content {
  max-width: 640px;
  will-change: transform, opacity;
}

.hero-title {
  margin: 28px 0 30px;
  font-size: clamp(64px, 8.4vw, 136px);
  /* The heading defaults (line-height 1.02, -0.015em) are what the approved
     design renders; they come from the shared .bc-theme heading rule. */
}

.hero-title .line {
  display: block;
  overflow: hidden;
  padding-bottom: 0.06em;
}

.hero-title .line-inner {
  display: block;
}

.hero-desc {
  max-width: 440px;
  margin-bottom: 44px;
  color: var(--ink-soft);
  font-size: 16.5px;
  line-height: 1.8;
}

.hero-actions {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 32px;
}

.hero-foot {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 36px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--ink-soft);
}

.hero-foot-cue {
  display: inline-flex;
  align-items: center;
  gap: 16px;
}

.cue-line {
  position: relative;
  width: 48px;
  height: 1px;
  background: var(--line);
  overflow: hidden;
}

.cue-line::after {
  content: "";
  position: absolute;
  inset: 0;
  background: var(--ink);
  transform: translateX(-100%);
  animation: cueSweep 2.4s cubic-bezier(0.65, 0, 0.35, 1) infinite;
}

@keyframes cueSweep {
  0% {
    transform: translateX(-100%);
  }
  50% {
    transform: translateX(0);
  }
  100% {
    transform: translateX(100%);
  }
}

/* Gold halo framing the bouquet */
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
  border: 1px solid rgba(212, 178, 122, 0.35);
}

.halo-ring--inner {
  inset: 9%;
  border-style: dashed;
  border-color: rgba(184, 101, 88, 0.16);
  animation: haloSpin 80s linear infinite;
}

@keyframes haloSpin {
  to {
    transform: rotate(360deg);
  }
}

/* ============================================================
   BOUQUET STORY (pinned)
============================================================= */
.story {
  position: relative;
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
  display: grid;
  grid-template-columns: minmax(0, 480px) 1fr;
  grid-template-rows: auto 1fr;
  padding-top: 128px;
  padding-bottom: 96px;
}

.story-head {
  grid-column: 1 / -1;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
}

.story-index {
  list-style: none;
  display: flex;
  gap: 34px;
}

.story-index li {
  display: flex;
  align-items: baseline;
  gap: 10px;
  color: var(--ink-soft);
  opacity: 0.4;
  transition: opacity 0.5s ease;
}

.story-index li.is-active {
  opacity: 1;
  color: var(--ink);
}

.story-index-num {
  font-family: var(--font-display);
  font-size: 19px;
  font-style: italic;
}

.story-index-label {
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.story-steps {
  grid-column: 1;
  position: relative;
  align-self: center;
  min-height: 380px;
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

.story-step-label {
  margin-bottom: 18px;
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--rosewood-dark);
}

.story-step h3 {
  margin-bottom: 24px;
  font-size: clamp(48px, 5.4vw, 88px);
  line-height: 0.95;
}

.story-step--reveal h3 {
  font-size: clamp(40px, 4.2vw, 64px);
}

.story-step p:not(.story-step-label) {
  max-width: 400px;
  margin-bottom: 32px;
  color: var(--ink-soft);
  font-size: 16px;
  line-height: 1.8;
}

/* Bloom picker: quiet text tabs */
.bloom-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 26px;
}

.bloom-option {
  padding: 6px 0;
  border: none;
  border-bottom: 1px solid transparent;
  background: none;
  color: var(--ink-soft);
  font-family: var(--font-sans);
  font-size: 14px;
  font-weight: 400;
  cursor: pointer;
  transition:
    color 0.3s ease,
    border-color 0.3s ease;
}

.bloom-option:hover {
  color: var(--ink);
}

.bloom-option.is-active {
  color: var(--ink);
  border-color: var(--ink);
}

.bloom-option:focus-visible,
.swatch:focus-visible {
  outline: 2px solid var(--rosewood);
  outline-offset: 4px;
}

.swatch-row {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 16px;
}

.swatch-caption {
  width: 92px;
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-soft);
}

.swatch-group {
  display: flex;
  gap: 12px;
}

.swatch {
  width: 28px;
  height: 28px;
  border: 3px solid var(--ivory);
  border-radius: 50%;
  background: var(--swatch);
  box-shadow: 0 0 0 1px var(--line);
  cursor: pointer;
  transition: box-shadow 0.3s ease, transform 0.3s var(--ease-out);
}

.swatch:hover {
  transform: scale(1.08);
}

.swatch.is-active {
  box-shadow: 0 0 0 1px var(--ink);
}

.story-features {
  list-style: none;
  display: flex;
  flex-wrap: wrap;
  gap: 6px 0;
  margin-bottom: 36px;
  color: var(--ink-soft);
  font-size: 14px;
}

.story-features li + li::before {
  content: "·";
  margin: 0 14px;
  color: var(--rosewood);
}

.story-progress-wrap {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 44px;
}

.story-progress {
  height: 1px;
  background: var(--line);
  overflow: hidden;
}

.story-progress-fill {
  display: block;
  height: 100%;
  background: var(--ink);
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
  gap: 72px;
  min-height: 0;
  margin-top: 56px;
}

.story--static .story-step {
  position: static;
}

.story--static .story-index,
.story--static .story-progress-wrap {
  display: none;
}

/* ============================================================
   HOW IT WORKS
============================================================= */
.process {
  position: relative;
  padding-block: var(--section-space);
}

.process-list {
  list-style: none;
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  margin-top: 88px;
  border-top: 1px solid var(--line);
}

.process-step {
  padding: 44px 44px 0 0;
}

.process-step + .process-step {
  padding-left: 44px;
  border-left: 1px solid var(--line);
}

.process-num {
  display: block;
  margin-bottom: 40px;
  font-family: var(--font-display);
  font-size: 54px;
  font-style: italic;
  line-height: 1;
  color: var(--rosewood);
}

.process-step h3 {
  margin-bottom: 16px;
  font-size: clamp(28px, 2.4vw, 36px);
}

.process-step p {
  max-width: 330px;
  color: var(--ink-soft);
  font-size: 15.5px;
  line-height: 1.8;
}

/* ============================================================
   PLANS (pinned horizontal track)
============================================================= */
.plans-section {
  position: relative;
  min-height: 100vh;
  min-height: 100svh;
  display: flex;
  align-items: center;
  overflow: hidden;
}

.plans-inner {
  width: 100%;
  display: grid;
  grid-template-columns: minmax(280px, 380px) minmax(0, 1fr);
  gap: 64px;
  align-items: center;
  padding: 120px 0 80px max(var(--gutter), calc(50% - 620px));
}

.plans-head h2 {
  margin: 22px 0 22px;
  font-size: clamp(42px, 4.6vw, 72px);
}

.plans-head p:not(.bc-label) {
  margin-bottom: 28px;
  color: var(--ink-soft);
  font-size: 15.5px;
  line-height: 1.8;
}

.plans-progress {
  width: 100%;
  max-width: 220px;
  height: 1px;
  margin-top: 48px;
  background: var(--line);
  overflow: hidden;
}

.plans-progress-fill {
  display: block;
  height: 100%;
  background: var(--ink);
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
  gap: 20px;
  width: max-content;
  padding-right: 6vw;
  will-change: transform;
}

.plans-card {
  width: clamp(300px, 25vw, 350px);
  flex-shrink: 0;
}

.plans-notice {
  grid-column: 1 / -1;
  max-width: 720px;
  padding: 12px 16px;
  border: 1px solid #eacb95;
  border-radius: 10px;
  background: #fff5df;
  color: #5b3a1e;
  font-size: 14px;
}

.plans-notice a {
  color: var(--rosewood-dark);
  font-weight: 600;
}

.plans-section--static .plans-viewport {
  overflow-x: auto;
  scroll-snap-type: x mandatory;
}

.plans-section--static .plans-card {
  scroll-snap-align: start;
}

/* ============================================================
   JOURNAL
============================================================= */
.journal {
  position: relative;
  padding-block: var(--section-space);
}

.journal-list {
  list-style: none;
  margin-top: 80px;
  border-top: 1px solid var(--line);
}

.journal-row {
  position: relative;
  isolation: isolate;
  display: grid;
  grid-template-columns: 72px 200px minmax(0, 1fr) 52px;
  align-items: center;
  gap: 24px;
  padding: 38px 0;
  border-bottom: 1px solid var(--line);
  color: var(--ink);
  text-decoration: none;
}

.journal-row::before {
  content: "";
  position: absolute;
  inset: 0 calc(var(--gutter) * -0.5);
  z-index: -1;
  background: var(--ivory-deep);
  transform: scaleY(0);
  transform-origin: bottom;
  transition: transform 0.6s var(--ease-out);
}

.journal-row:hover::before,
.journal-row:focus-visible::before {
  transform: scaleY(1);
}

.journal-row:focus-visible {
  outline: none;
}

.journal-index {
  font-family: var(--font-display);
  font-size: 22px;
  font-style: italic;
  color: var(--ink-soft);
}

.journal-category {
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--ink-soft);
}

.journal-title {
  font-family: var(--font-display);
  font-size: clamp(28px, 2.9vw, 44px);
  font-weight: 500;
  line-height: 1.1;
  letter-spacing: -0.01em;
  transition:
    transform 0.6s var(--ease-out),
    color 0.4s ease;
}

.journal-row:hover .journal-title,
.journal-row:focus-visible .journal-title {
  transform: translateX(12px);
  color: var(--rosewood-dark);
}

.journal-arrow {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border: 1px solid var(--line);
  border-radius: 50%;
  transition:
    background 0.4s var(--ease-out),
    border-color 0.4s var(--ease-out);
}

.journal-arrow svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: var(--ink);
  stroke-width: 1.5;
  stroke-linecap: round;
  stroke-linejoin: round;
  transition:
    stroke 0.4s ease,
    transform 0.5s var(--ease-out);
}

.journal-row:hover .journal-arrow,
.journal-row:focus-visible .journal-arrow {
  background: var(--ink);
  border-color: var(--ink);
}

.journal-row:hover .journal-arrow svg,
.journal-row:focus-visible .journal-arrow svg {
  stroke: var(--ivory);
  transform: translateX(2px);
}

/* ============================================================
   ABOUT US
============================================================= */
.about-section {
  position: relative;
  padding-block: var(--section-space);
  background: linear-gradient(
    180deg,
    transparent,
    rgba(235, 229, 221, 0.75) 16%,
    rgba(235, 229, 221, 0.75) 84%,
    transparent
  );
}

.about-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 80px;
  margin-top: 88px;
}

.about-card {
  padding-top: 32px;
  border-top: 1px solid var(--line);
}

.landing-page .about-title {
  margin-bottom: 26px;
  font-family: var(--font-sans);
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--rosewood-dark);
}

.about-text {
  font-family: var(--font-display);
  font-size: clamp(24px, 2.15vw, 31px);
  font-weight: 400;
  line-height: 1.42;
  color: var(--ink);
}

.about-rule {
  height: 1px;
  margin-top: 100px;
  background: linear-gradient(90deg, var(--gold), rgba(212, 178, 122, 0));
  transform-origin: left;
}

/* ============================================================
   CLOSING CTA
============================================================= */
.cta-panel {
  position: relative;
  display: flex;
  align-items: flex-end;
  min-height: 100vh;
  min-height: 100svh;
  padding: 160px 0 14vh;
  text-align: center;
}

.cta-content {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.cta-panel h2 {
  max-width: 980px;
  margin-bottom: 48px;
  font-size: clamp(54px, 7.2vw, 120px);
  line-height: 0.95;
}

/* ============================================================
   FOOTER
============================================================= */
.footer {
  position: relative;
  z-index: 15;
  padding: 120px 0 40px;
  background: var(--espresso);
  color: var(--ivory);
}

.footer-content {
  display: grid;
  grid-template-columns: 1.6fr 1fr 1fr 1fr;
  gap: 64px;
}

.footer-brand {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.footer-logo {
  display: flex;
  align-items: center;
  gap: 12px;
  font-family: var(--font-display);
  font-size: 30px;
  font-weight: 600;
}

.footer-logo img {
  object-fit: contain;
}

.footer-brand p {
  max-width: 320px;
  color: rgba(250, 248, 245, 0.55);
  font-size: 14.5px;
  line-height: 1.8;
}

.social-links {
  list-style: none;
  display: flex;
  flex-wrap: wrap;
  gap: 10px 24px;
}

.social-link {
  color: rgba(250, 248, 245, 0.65);
  font-size: 12px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  text-decoration: none;
  transition: color 0.3s;
}

.social-link:hover {
  color: var(--ivory);
}

.footer-section h4 {
  margin-bottom: 26px;
  font-size: 11.5px;
  font-weight: 500;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: rgba(250, 248, 245, 0.45);
}

.footer-section ul {
  list-style: none;
}

.footer-section li {
  margin-bottom: 14px;
}

.footer-section a {
  color: rgba(250, 248, 245, 0.78);
  font-size: 15px;
  text-decoration: none;
  transition: color 0.3s;
}

.footer-section a:hover {
  color: var(--ivory);
}

.footer a:focus-visible {
  outline: 2px solid var(--gold);
  outline-offset: 3px;
}

.footer-wordmark {
  margin: 110px 0 28px;
  font-family: var(--font-display);
  font-size: clamp(80px, 19.5vw, 300px);
  font-weight: 500;
  line-height: 0.8;
  letter-spacing: -0.035em;
  text-align: center;
  white-space: nowrap;
  color: rgba(250, 248, 245, 0.92);
}

.footer-bottom {
  padding-top: 28px;
  border-top: 1px solid rgba(250, 248, 245, 0.1);
  color: rgba(250, 248, 245, 0.45);
  font-size: 13px;
}

/* ============================================================
   RESPONSIVE
============================================================= */
@media (max-width: 1100px) {
  .nav-links {
    gap: 24px;
  }
}

@media (max-width: 968px) {
  /* Hero: copy on top, bouquet beneath */
  .hero {
    justify-content: flex-start;
    padding-top: 128px;
  }

  .hero-title {
    font-size: clamp(54px, 13vw, 92px);
  }

  .hero-desc {
    margin-bottom: 32px;
  }

  .hero-foot {
    display: none;
  }

  .hero-halo {
    top: 72%;
    right: 50%;
    width: 86vw;
    transform: translate(50%, -50%);
    opacity: 0.7;
  }

  /* Story: text card below the bouquet */
  .story-inner {
    grid-template-columns: 1fr;
    grid-template-rows: auto 1fr auto;
    padding-top: 92px;
    padding-bottom: 56px;
  }

  .story-index {
    gap: 16px;
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
    padding: 26px 24px;
    border: 1px solid var(--line-soft);
    border-radius: 20px;
    background: rgba(244, 241, 236, 0.9);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
  }

  .story-step h3,
  .story-step--reveal h3 {
    margin-bottom: 12px;
    font-size: 38px;
  }

  .story-step p:not(.story-step-label) {
    margin-bottom: 18px;
    font-size: 14.5px;
    line-height: 1.7;
  }

  .story-step-label {
    margin-bottom: 10px;
  }

  .swatch-caption {
    width: 78px;
  }

  .story-features {
    margin-bottom: 20px;
  }

  .story-progress-wrap {
    bottom: 28px;
  }

  .story--static .story-step {
    position: static;
  }

  .process-list {
    grid-template-columns: 1fr;
    margin-top: 56px;
  }

  .process-step,
  .process-step + .process-step {
    padding: 36px 0;
    border-left: none;
  }

  .process-step + .process-step {
    border-top: 1px solid var(--line);
  }

  .process-num {
    margin-bottom: 22px;
    font-size: 44px;
  }

  .plans-section {
    min-height: auto;
  }

  .plans-inner {
    grid-template-columns: 1fr;
    gap: 28px;
    padding: 96px 0 72px var(--gutter);
  }

  .plans-head {
    padding-right: var(--gutter);
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
    margin-right: var(--gutter);
  }

  .journal-list {
    margin-top: 48px;
  }

  .journal-row {
    grid-template-columns: 40px minmax(0, 1fr) 44px;
    grid-template-areas:
      "index category arrow"
      "index title arrow";
    gap: 6px 16px;
    padding: 28px 0;
  }

  .journal-index {
    grid-area: index;
    align-self: start;
  }

  .journal-category {
    grid-area: category;
  }

  .journal-title {
    grid-area: title;
    font-size: 28px;
  }

  .journal-arrow {
    grid-area: arrow;
    width: 44px;
    height: 44px;
  }

  .about-grid {
    grid-template-columns: 1fr;
    gap: 56px;
    margin-top: 56px;
  }

  .about-rule {
    margin-top: 64px;
  }

  .footer-content {
    grid-template-columns: 1fr 1fr;
    gap: 48px 32px;
  }

  .footer-brand {
    grid-column: 1 / -1;
  }

  .footer-wordmark {
    margin-top: 72px;
  }
}

@media (max-width: 640px) {
  /* Login moves into the menu; Register stays in the bar */
  .nav-buttons {
    gap: 10px;
  }

  .hero-actions {
    gap: 24px;
  }

  .cta-panel h2 {
    font-size: 48px;
  }

  .back-to-top {
    right: calc(16px + env(safe-area-inset-right, 0px));
    bottom: calc(16px + env(safe-area-inset-bottom, 0px));
    width: 46px;
    height: 46px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .halo-ring--inner,
  .cue-line::after {
    animation: none;
  }
}
</style>
