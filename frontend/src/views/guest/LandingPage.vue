<template>
  <div class="landing-page">
    <div class="bg-blob blob-rose" aria-hidden="true"></div>
    <div class="bg-blob blob-sage" aria-hidden="true"></div>
    <div class="grain-overlay" aria-hidden="true"></div>

    <!-- ============================================================
         PERSISTENT 3D FLOWER LAYER
         Fixed, full-viewport, travels the entire page via GSAP
         ScrollTrigger. Never unmounted, never disappears.
    ============================================================= -->
    <div class="flower-stage-fixed" aria-hidden="true">
      <span class="drift-petal petal-a" aria-hidden="true"></span>
      <span class="drift-petal petal-b" aria-hidden="true"></span>
      <span class="drift-petal petal-c" aria-hidden="true"></span>
      <span class="drift-petal petal-d" aria-hidden="true"></span>
      <span class="drift-petal petal-e" aria-hidden="true"></span>
      <canvas ref="flowerCanvas" class="flower-canvas"></canvas>
    </div>

    <!-- Navigation -->
    <nav class="navbar" :class="{ 'navbar--scrolled': navScrolled }">
      <router-link to="/" class="logo">
        <span
          ><img
            src="../../../public/bloomcraft-blankBg.png"
            alt="Bloomcraft Logo"
            width="50"
            height="50"
        /></span>
        <span>BloomCraft</span>
      </router-link>
      <div class="nav-links">
        <router-link to="/shop">Shop</router-link>
        <a href="#features" @click.prevent="scrollToSection('features')"
          >Features</a
        >
        <a href="#how-it-works" @click.prevent="scrollToSection('how-it-works')"
          >How It Works</a
        >
        <a
          href="#vendors"
          @click.prevent="scrollAndHighlight('register-vendor')"
          >For Vendors</a
        >
        <a href="#blog" @click.prevent="scrollToSection('blog')">Blog</a>
      </div>
      <div class="nav-buttons">
        <template v-if="!isAuthenticated">
          <router-link to="/guest/login" class="btn-login">Login</router-link>
          <router-link to="/guest/register" class="btn-register"
            >Register</router-link
          >
        </template>
      </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" ref="heroSection">
      <div class="hero-glow" aria-hidden="true"></div>

      <div class="hero-copy hero-copy--top reveal-hero">
        <span class="eyebrow">Bespoke florals, built by you</span>
        <h1>
          Create your perfect
          <span class="highlight">bouquet</span>
        </h1>
      </div>

      <!-- Spacer only: keeps original hero height/rhythm.
           The actual flower now lives in .flower-stage-fixed above. -->
      <div class="hero-stage" aria-hidden="true">
        <div class="flower-podium"></div>
      </div>

      <div class="hero-copy hero-copy--bottom reveal-hero">
        <p>
          Where vendors meet creativity. Design custom flower arrangements in 3D
          or let our AI suggest the perfect bloom for every occasion.
        </p>
        <router-link to="/guest/register" class="btn-register btn-hero"
          >Get Started</router-link
        >
        <span class="scroll-cue">Scroll to explore</span>
      </div>
    </section>

    <!-- Clients/Partners Section -->
    <section class="clients" ref="clientsSection">
      <h2 class="reveal">Trusted by Flower Lovers</h2>
      <p class="reveal">
        Join {{ stats.vendors }}+ vendors and thousands of happy customers
      </p>
      <div class="clients-grid">
        <div v-for="n in 5" :key="n" class="client-logo reveal">
          Logo {{ n }}
        </div>
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features" ref="featuresSection">
      <div class="features-header">
        <span class="eyebrow reveal">Why BloomCraft</span>
        <h2 class="reveal">Everything you need to bloom</h2>
        <p class="reveal">
          Powerful features for vendors and delightful experiences for customers
        </p>
      </div>
      <div class="features-grid">
        <div
          v-for="feature in features"
          :key="feature.id"
          class="feature-card reveal"
        >
          <div class="feature-icon">{{ feature.icon }}</div>
          <h3>{{ feature.title }}</h3>
          <p>{{ feature.description }}</p>
        </div>
      </div>
    </section>

    <!-- Content Section 1 -->
    <section class="content-section" ref="contentSection1">
      <div class="content-text reveal">
        <span class="eyebrow">3D Design Studio</span>
        <h2>Design in 3D, deliver with love</h2>
        <p>
          Our revolutionary 3D customization tool lets you become the designer.
          Choose flowers, arrange them in real-time, adjust colors and sizes,
          and visualize your perfect bouquet before placing your order.
        </p>
        <p>Every arrangement is unique, just like your story.</p>
        <button class="btn-learn-more" @click="handleLearnMore('3d-designer')">
          Explore 3D Designer
        </button>
      </div>
      <div class="content-image content-image--ghost reveal">
        <img
          src="../../../public/3d flower.png"
          alt="Bloomcraft Logo"
          width="800"
          height="500"
        />
      </div>
    </section>

    <!-- Stats Section -->
    <section id="vendors" class="stats" ref="statsSection">
      <div class="stats-grid">
        <div
          v-for="stat in statsData"
          :key="stat.label"
          class="stat-item reveal"
        >
          <div class="stat-icon">{{ stat.icon }}</div>
          <div class="stat-number">{{ stat.number }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </div>
      </div>
    </section>

    <!-- Content Section 2 -->
    <section
      id="how-it-works"
      class="content-section content-section--reverse"
      ref="contentSection2"
    >
      <div class="content-text reveal">
        <span class="eyebrow">AI Concierge</span>
        <h2>AI-powered recommendations</h2>
        <p>
          Don't know where to start? Our intelligent AI analyzes the occasion,
          season, recipient preferences, and current trends to suggest the
          perfect arrangement.
        </p>
        <p>
          Get inspired by thousands of beautiful combinations, or let our AI
          create something uniquely yours.
        </p>
        <button class="btn-learn-more" @click="handleLearnMore('ai-designer')">
          Try AI Designer
        </button>
      </div>
      <div class="content-image content-image--ghost reveal">
        <img
          src="../../../public/ai power.jpg"
          alt="Bloomcraft Logo"
          width="800"
          height="500"
        />
      </div>
    </section>

    <!-- Blog Section -->
    <section id="blog" class="blog" ref="blogSection">
      <div class="blog-header">
        <span class="eyebrow reveal">The Journal</span>
        <h2 class="reveal">Fresh insights from our garden</h2>
        <p class="reveal">
          Tips, trends, and stories from the world of flowers
        </p>
      </div>
      <div class="blog-grid">
        <div v-for="post in blogPosts" :key="post.id" class="blog-card reveal">
          <div class="blog-image">Blog Image {{ post.id }}<br />400x250px</div>
          <div class="blog-content">
            <h3>{{ post.title }}</h3>
            <a href="#" @click.prevent="readBlog(post.id)" class="blog-link"
              >Read more →</a
            >
          </div>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="cta" ref="ctaSection">
      <h2 class="reveal">Ready to create something beautiful?</h2>
      <router-link to="/guest/register" class="btn-cta reveal"
        >Start Designing Now</router-link
      >
    </section>

    <!-- Footer -->
    <footer class="footer" ref="footerSection">
      <div class="footer-content">
        <div class="footer-brand">
          <div class="logo">
            <span
              ><img
                src="../../../public/bloomcraft-darkmode-removebg.png"
                alt="Bloomcraft Logo"
                width="60"
                height="60"
            /></span>
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
              <a :href="link.url" @click.prevent="handleFooterLink(link.url)">{{
                link.name
              }}</a>
            </li>
          </ul>
        </div>
        <div class="footer-section">
          <h4>Support</h4>
          <ul>
            <li v-for="link in supportLinks" :key="link.name">
              <a :href="link.url" @click.prevent="handleFooterLink(link.url)">{{
                link.name
              }}</a>
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
              <a href="#" @click.prevent="handleFooterLink('#pricing')"
                >Pricing</a
              >
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
import { ref, computed, onMounted, onBeforeUnmount } from "vue";
import { useRouter } from "vue-router";
import * as THREE from "three";
import { gsap } from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";
import Lenis from "lenis";

gsap.registerPlugin(ScrollTrigger);

const router = useRouter();

// ---- Section refs (used only to anchor ScrollTriggers — no
// structural/functional changes to the sections themselves) ----
const heroSection = ref(null);
const clientsSection = ref(null);
const featuresSection = ref(null);
const contentSection1 = ref(null);
const statsSection = ref(null);
const contentSection2 = ref(null);
const blogSection = ref(null);
const ctaSection = ref(null);
const footerSection = ref(null);

const flowerCanvas = ref(null);
const navScrolled = ref(false);

// Reactive Data
const stats = ref({
  vendors: 500,
  customers: 5000,
  designs: 10000,
  rating: 4.9,
});

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
  { icon: "🌹", number: "10,000+", label: "Custom Designs" },
  { icon: "👥", number: "5,000+", label: "Happy Customers" },
  { icon: "🏪", number: "500+", label: "Vendor Partners" },
  { icon: "⭐", number: "4.9/5", label: "Average Rating" },
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

// Methods (unchanged — same routes, same behavior)
const scrollToSection = (sectionId) => {
  const element = document.getElementById(sectionId);
  if (element) {
    element.scrollIntoView({ behavior: "smooth" });
  }
};

const scrollAndHighlight = (sectionId) => {
  const element = document.getElementById(sectionId);

  if (element) {
    element.scrollIntoView({ behavior: "smooth" });

    element.classList.add("highlight-vendor");

    setTimeout(() => {
      element.classList.remove("highlight-vendor");
    }, 2000);
  }
};

const handleLearnMore = (type) => {
  console.log("Learn more about:", type);
  router.push("/guest/register");
};

const readBlog = (postId) => {
  console.log("Reading blog post:", postId);
};

const handleFooterLink = (url) => {
  console.log("Footer link clicked:", url);
};

// ==========================================================
// PERSISTENT 3D FLOWER — scene, journey across the whole page,
// pointer interaction. No routes/links/structure touched.
// ==========================================================
let renderer = null;
let scene = null;
let camera = null;
let rig = null; // outer group — position/rotation driven by scroll journey
let flowerGroup = null; // inner group — idle bob + pointer tilt + slow spin
let flowerRings = [];
let particles = null;
let rafId = null;
let lenis = null;
let lenisRafId = null;
let prefersReducedMotion = false;
let isTouchDevice = false;

let isScrolling = false;
let scrollIdleTimeout = null;
const pointer = { x: 0, y: 0 };
const tilt = { x: 0, z: 0 };
const journeyTriggers = [];

function petalMaterial(color) {
  return new THREE.MeshStandardMaterial({
    color,
    roughness: 0.55,
    metalness: 0.05,
    side: THREE.DoubleSide,
  });
}

function buildFlower() {
  const group = new THREE.Group();
  const rings = [];

  const ringConfigs = [
    { count: 6, spin: 0.0, tilt: 0.3, length: 0.9, color: 0xf7ddd4 },
    { count: 8, spin: 0.35, tilt: 0.7, length: 1.25, color: 0xefbdb3 },
    { count: 10, spin: 0.18, tilt: 1.1, length: 1.55, color: 0xe4988f },
  ];

  ringConfigs.forEach(({ count, spin, tilt: ringTilt, length, color }) => {
    const material = petalMaterial(color);
    const petalGeo = new THREE.SphereGeometry(1, 20, 20);
    const ringGroup = new THREE.Group();

    for (let i = 0; i < count; i++) {
      const pivot = new THREE.Object3D();
      pivot.rotation.y = (i / count) * Math.PI * 2 + spin;

      const petal = new THREE.Mesh(petalGeo, material);
      petal.scale.set(0.42, length, 0.1);
      petal.position.set(0, length * 0.42, 0);
      petal.rotation.x = ringTilt;

      pivot.add(petal);
      ringGroup.add(pivot);
    }

    group.add(ringGroup);
    rings.push(ringGroup);
  });

  const center = new THREE.Mesh(
    new THREE.SphereGeometry(0.3, 24, 24),
    new THREE.MeshStandardMaterial({ color: 0xf2c879, roughness: 0.6 }),
  );
  group.add(center);

  const leafMaterial = new THREE.MeshStandardMaterial({
    color: 0x8fae82,
    roughness: 0.6,
    side: THREE.DoubleSide,
  });
  const leafGeo = new THREE.SphereGeometry(1, 16, 16);
  for (let i = 0; i < 3; i++) {
    const pivot = new THREE.Object3D();
    pivot.rotation.y = (i / 3) * Math.PI * 2 + 0.4;

    const leaf = new THREE.Mesh(leafGeo, leafMaterial);
    leaf.scale.set(0.3, 0.9, 0.06);
    leaf.position.set(0, -0.85, 0);
    leaf.rotation.x = -1.3;

    pivot.add(leaf);
    group.add(pivot);
  }

  group.scale.setScalar(1.35);
  return { group, rings };
}

function radialTexture(innerColor, outerColor) {
  const size = 256;
  const canvas = document.createElement("canvas");
  canvas.width = size;
  canvas.height = size;
  const ctx = canvas.getContext("2d");
  const gradient = ctx.createRadialGradient(
    size / 2,
    size / 2,
    0,
    size / 2,
    size / 2,
    size / 2,
  );
  gradient.addColorStop(0, innerColor);
  gradient.addColorStop(1, outerColor);
  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, size, size);
  return new THREE.CanvasTexture(canvas);
}

function buildGroundShadow() {
  const texture = radialTexture("rgba(30,20,18,0.35)", "rgba(30,20,18,0)");
  const material = new THREE.MeshBasicMaterial({
    map: texture,
    transparent: true,
    depthWrite: false,
  });
  const shadow = new THREE.Mesh(new THREE.PlaneGeometry(4.2, 2.6), material);
  shadow.rotation.x = -Math.PI / 2;
  shadow.position.y = -1.9;
  return shadow;
}

function buildBloomGlow() {
  const texture = radialTexture("rgba(255,225,210,0.9)", "rgba(255,225,210,0)");
  const material = new THREE.SpriteMaterial({
    map: texture,
    transparent: true,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
  });
  const sprite = new THREE.Sprite(material);
  sprite.scale.set(5.5, 5.5, 1);
  sprite.position.set(0, 0.2, -0.6);
  return sprite;
}

function buildParticles() {
  const count = 70;
  const positions = new Float32Array(count * 3);
  for (let i = 0; i < count; i++) {
    positions[i * 3] = (Math.random() - 0.5) * 7;
    positions[i * 3 + 1] = (Math.random() - 0.5) * 5.5;
    positions[i * 3 + 2] = (Math.random() - 0.5) * 4;
  }
  const geometry = new THREE.BufferGeometry();
  geometry.setAttribute("position", new THREE.BufferAttribute(positions, 3));
  const material = new THREE.PointsMaterial({
    color: 0xf5c6c0,
    size: 0.05,
    transparent: true,
    opacity: 0.6,
  });
  return new THREE.Points(geometry, material);
}

function initThreeScene() {
  const canvas = flowerCanvas.value;
  if (!canvas) return;

  prefersReducedMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
  ).matches;
  isTouchDevice = window.matchMedia("(pointer: coarse)").matches;

  const width = window.innerWidth;
  const height = window.innerHeight;

  scene = new THREE.Scene();
  camera = new THREE.PerspectiveCamera(38, width / height, 0.1, 100);
  camera.position.set(1.1, 0.4, 6);

  renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
  renderer.setSize(width, height);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.15;

  scene.add(new THREE.AmbientLight(0xfff2ec, 0.9));

  const key = new THREE.DirectionalLight(0xffffff, 1.2);
  key.position.set(3, 4, 5);
  scene.add(key);

  const rimLight = new THREE.PointLight(0xffd9c9, 0.9, 10);
  rimLight.position.set(-3, 1, -2);
  scene.add(rimLight);

  scene.add(buildBloomGlow());
  scene.add(buildGroundShadow());

  const built = buildFlower();
  flowerGroup = built.group;
  flowerRings = built.rings;

  rig = new THREE.Group();
  rig.position.set(1.1, 0, 0); // hero start: center-right
  rig.add(flowerGroup);
  scene.add(rig);

  particles = buildParticles();
  scene.add(particles);

  animateFrame();
}

function animateFrame() {
  rafId = requestAnimationFrame(animateFrame);
  if (!renderer || !scene || !camera) return;

  if (flowerGroup) {
    flowerGroup.position.y = Math.sin(Date.now() * 0.0006) * 0.08;

    if (!isScrolling) {
      flowerGroup.userData.idleY = (flowerGroup.userData.idleY || 0) + 0.0009;

      if (!isTouchDevice) {
        tilt.x += (pointer.y * 0.22 - tilt.x) * 0.04;
        tilt.z += (pointer.x * -0.18 - tilt.z) * 0.04;
      }
      flowerGroup.rotation.x = tilt.x;
      flowerGroup.rotation.z = tilt.z;
      flowerGroup.rotation.y = flowerGroup.userData.idleY;
    }
  }

  if (flowerRings.length) {
    flowerRings.forEach((ringGroup, i) => {
      ringGroup.rotation.y += 0.0004 * (i + 1) * (i % 2 === 0 ? 1 : -1);
    });
  }

  if (particles) {
    particles.rotation.y += 0.0006;
  }

  renderer.render(scene, camera);
}

/**
 * The flower's journey down the page. Each stage nudges the rig
 * (position/rotation/scale), the camera (subtle zoom), and the
 * canvas opacity (recede during non-focal sections) using a GSAP
 * scrub tween tied to that section's own entrance window. Because
 * each tween starts from whatever the rig's current value is,
 * consecutive stages chain into one continuous, smooth path with
 * no snapping — exactly the "flower travels down the page while
 * alternating sides" behavior requested.
 */
function stage(
  trigger,
  { x, y = 0, scaleAll = 1, rotY, camZ, canvasOpacity = 1 },
) {
  if (!trigger) return;
  const tl = gsap.timeline({
    scrollTrigger: {
      trigger,
      start: "top 78%",
      end: "top 22%",
      scrub: 0.7,
    },
  });
  journeyTriggers.push(tl.scrollTrigger);

  tl.to(rig.position, { x, y, ease: "none" }, 0);
  tl.to(rig.scale, { x: scaleAll, y: scaleAll, z: scaleAll, ease: "none" }, 0);
  if (rotY !== undefined) {
    tl.to(rig.rotation, { y: rotY, ease: "none" }, 0);
  }
  if (camera) {
    tl.to(camera.position, { z: camZ, ease: "none" }, 0);
  }
  if (flowerCanvas.value) {
    tl.to(flowerCanvas.value, { opacity: canvasOpacity, ease: "none" }, 0);
  }
}

function setupFlowerJourney() {
  if (!rig || !camera || prefersReducedMotion) return;

  const vw = window.innerWidth;
  const isMobile = vw < 640;
  const isTablet = vw >= 640 && vw < 1024;
  const spread = isMobile ? 0.9 : isTablet ? 1.4 : 1.9;
  const spreadNear = spread * 0.68; // for stages where flower sits behind real photos

  // Hero — handled by its own dedicated timeline below (start position +
  // text fade). Everything after hero continues the same rig from there.

  stage(clientsSection.value, {
    x: spread,
    y: -0.15,
    scaleAll: 0.55,
    rotY: Math.PI * 0.35,
    camZ: 6.3,
    canvasOpacity: 0.32,
  });

  stage(featuresSection.value, {
    x: -spread,
    y: 0.1,
    scaleAll: 0.5,
    rotY: Math.PI * 0.75,
    camZ: 6.3,
    canvasOpacity: 0.26,
  });

  stage(contentSection1.value, {
    x: spreadNear,
    y: 0,
    scaleAll: 0.9,
    rotY: Math.PI * 1.15,
    camZ: 5.6,
    canvasOpacity: 0.55,
  });

  stage(statsSection.value, {
    x: -spread,
    y: 0.05,
    scaleAll: 1.15,
    rotY: Math.PI * 1.6,
    camZ: 4.9,
    canvasOpacity: 0.9,
  });

  stage(contentSection2.value, {
    x: -spreadNear,
    y: 0,
    scaleAll: 0.9,
    rotY: Math.PI * 2.05,
    camZ: 5.6,
    canvasOpacity: 0.55,
  });

  stage(blogSection.value, {
    x: spread,
    y: -0.1,
    scaleAll: 0.5,
    rotY: Math.PI * 2.5,
    camZ: 6.3,
    canvasOpacity: 0.28,
  });

  stage(ctaSection.value, {
    x: 0,
    y: 0,
    scaleAll: 1.2,
    rotY: Math.PI * 2.9,
    camZ: 4.7,
    canvasOpacity: 0.85,
  });

  // Fade the flower out gently as the footer arrives
  if (footerSection.value && flowerCanvas.value) {
    const footTl = gsap.timeline({
      scrollTrigger: {
        trigger: footerSection.value,
        start: "top 85%",
        end: "top 35%",
        scrub: 0.7,
      },
    });
    footTl.to(flowerCanvas.value, { opacity: 0, ease: "none" }, 0);
    journeyTriggers.push(footTl.scrollTrigger);
  }
}

function setupHeroTimeline() {
  if (!heroSection.value || !rig || !camera || prefersReducedMotion) return;

  const heroTextEls = heroSection.value.querySelectorAll(".hero-copy");

  const tl = gsap.timeline({
    scrollTrigger: {
      trigger: heroSection.value,
      start: "top top",
      end: "+=140%",
      scrub: 0.6,
      onUpdate: () => {
        isScrolling = true;
        clearTimeout(scrollIdleTimeout);
        scrollIdleTimeout = setTimeout(() => {
          isScrolling = false;
        }, 250);
      },
    },
  });
  journeyTriggers.push(tl.scrollTrigger);

  tl.to(rig.position, { x: 1.1, y: 0, ease: "power1.inOut" }, 0)
    .to(rig.rotation, { y: Math.PI * 0.3, ease: "none" }, 0)
    .to(camera.position, { z: 5.4, ease: "none" }, 0)
    .to(heroTextEls, { autoAlpha: 0, y: -26, ease: "none" }, 0.1);
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

function setupSectionReveals() {
  gsap.utils.toArray(".reveal").forEach((el) => {
    gsap.fromTo(
      el,
      { autoAlpha: 0, y: 36 },
      {
        autoAlpha: 1,
        y: 0,
        duration: 0.9,
        ease: "power3.out",
        scrollTrigger: {
          trigger: el,
          start: "top 88%",
          toggleActions: "play none none reverse",
        },
      },
    );
  });
}

function handleWindowScroll() {
  navScrolled.value = window.scrollY > 24;
}

function handlePointerMove(event) {
  pointer.x = (event.clientX / window.innerWidth) * 2 - 1;
  pointer.y = (event.clientY / window.innerHeight) * 2 - 1;
}

function handleResize() {
  if (!renderer || !camera) return;
  const width = window.innerWidth;
  const height = window.innerHeight;
  camera.aspect = width / height;
  camera.updateProjectionMatrix();
  renderer.setSize(width, height);
}

function initSmoothScroll() {
  lenis = new Lenis({
    duration: 1.1,
    easing: (t) => 1 - Math.pow(1 - t, 3),
  });

  const raf = (time) => {
    lenis.raf(time);
    ScrollTrigger.update();
    lenisRafId = requestAnimationFrame(raf);
  };
  lenisRafId = requestAnimationFrame(raf);
  lenis.on("scroll", ScrollTrigger.update);
}

onMounted(() => {
  initThreeScene();
  setupHeroTimeline();
  setupFlowerJourney();
  setupBackgroundParallax();
  setupSectionReveals();
  initSmoothScroll();
  window.addEventListener("mousemove", handlePointerMove, { passive: true });
  window.addEventListener("resize", handleResize);
  window.addEventListener("scroll", handleWindowScroll, { passive: true });
  handleWindowScroll();
});

onBeforeUnmount(() => {
  window.removeEventListener("mousemove", handlePointerMove);
  window.removeEventListener("resize", handleResize);
  window.removeEventListener("scroll", handleWindowScroll);
  clearTimeout(scrollIdleTimeout);

  if (rafId) cancelAnimationFrame(rafId);
  if (lenisRafId) cancelAnimationFrame(lenisRafId);
  if (lenis) lenis.destroy();
  journeyTriggers.forEach((st) => st && st.kill());
  ScrollTrigger.getAll().forEach((trigger) => trigger.kill());
  if (renderer) renderer.dispose();
});
</script>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800&family=Poppins:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap");

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.landing-page {
  --ivory: #f8f4ef;
  --ivory-deep: #f1eae2;
  --ink: #1c1917;
  --ink-soft: #6f6a64;
  --rosewood: #a5584c;
  --rosewood-dark: #7f4238;
  --gold: #c9a66b;
  --espresso: #16130f;
  --line: rgba(28, 25, 23, 0.08);

  /* glass variants so the fixed flower layer can show through */
  --ivory-glass: rgba(248, 244, 239, 0.86);
  --ivory-deep-glass: rgba(241, 234, 226, 0.86);
  --espresso-glass: rgba(22, 19, 15, 0.86);

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
  letter-spacing: -0.01em;
}

.eyebrow {
  display: inline-block;
  font-family: "Poppins", sans-serif;
  font-size: 12.5px;
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--rosewood);
  margin-bottom: 14px;
}

/* Ambient background: blurred glows + grain, sits behind everything */
.bg-blob {
  position: absolute;
  border-radius: 50%;
  filter: blur(70px);
  pointer-events: none;
  z-index: 0;
}

.blob-rose {
  top: -10%;
  right: -8%;
  width: 46vw;
  height: 46vw;
  background: radial-gradient(
    circle,
    rgba(197, 130, 118, 0.28),
    transparent 70%
  );
}

.blob-sage {
  top: 55%;
  left: -12%;
  width: 38vw;
  height: 38vw;
  background: radial-gradient(
    circle,
    rgba(143, 174, 130, 0.18),
    transparent 70%
  );
}

.grain-overlay {
  position: fixed;
  inset: 0;
  z-index: 2000;
  pointer-events: none;
  opacity: 0.05;
  mix-blend-mode: overlay;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
}

/* ============================================================
   PERSISTENT FLOWER LAYER — fixed full-viewport, travels the
   whole page. Sits above section backgrounds, below section
   copy (which gets z-index below) and below the nav.
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

/* Navigation */
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  background: rgba(248, 244, 239, 0.7);
  backdrop-filter: blur(14px);
  padding: 1.5rem 5%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 1000;
  border-bottom: 1px solid transparent;
  transition:
    padding 0.4s ease,
    background 0.4s ease,
    border-color 0.4s ease,
    box-shadow 0.4s ease;
}

.navbar--scrolled {
  padding: 0.85rem 5%;
  background: rgba(248, 244, 239, 0.92);
  border-bottom-color: var(--line);
  box-shadow: 0 8px 30px rgba(28, 25, 23, 0.06);
}

.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  font-family: "Montserrat", sans-serif;
  font-size: 22px;
  font-weight: 700;
  color: var(--ink);
  text-decoration: none;
  letter-spacing: -0.01em;
}

.nav-links {
  display: flex;
  gap: 40px;
  align-items: center;
}

.nav-links a {
  position: relative;
  color: var(--ink);
  text-decoration: none;
  font-size: 14px;
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
  height: 1px;
  background: var(--rosewood);
  transition: width 0.35s ease;
}

.nav-links a:hover {
  color: var(--rosewood);
}

.nav-links a:hover::after {
  width: 100%;
}

.nav-buttons {
  display: flex;
  gap: 14px;
}

.btn-login {
  padding: 11px 26px;
  background: transparent;
  color: var(--ink);
  border: 1px solid var(--line);
  border-radius: 999px;
  font-family: "Poppins", sans-serif;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-block;
}

.btn-login:hover {
  border-color: var(--ink);
  transform: translateY(-2px);
}

.btn-register {
  padding: 11px 26px;
  background: var(--ink);
  color: var(--ivory);
  border: none;
  border-radius: 999px;
  font-family: "Poppins", sans-serif;
  font-size: 13.5px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  text-decoration: none;
  display: inline-block;
}

.btn-register:hover {
  background: var(--rosewood-dark);
  transform: translateY(-2px);
  box-shadow: 0 10px 24px rgba(127, 66, 56, 0.28);
}

/* Hero Section */
.hero {
  position: relative;
  margin-top: 0;
  padding: 190px 5% 100px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0;
  min-height: 100vh;
  overflow: hidden;
  text-align: center;
  z-index: 1;
}

.hero-glow {
  position: absolute;
  inset: -10%;
  background:
    radial-gradient(
      55% 50% at 50% 40%,
      rgba(197, 130, 118, 0.22) 0%,
      rgba(197, 130, 118, 0) 70%
    ),
    radial-gradient(
      40% 40% at 12% 85%,
      rgba(143, 174, 130, 0.14) 0%,
      rgba(143, 174, 130, 0) 70%
    );
  pointer-events: none;
  z-index: 0;
}

.hero-copy {
  position: relative;
  z-index: 20;
  max-width: 640px;
}

.hero-copy--top h1 {
  font-size: clamp(40px, 6vw, 76px);
  line-height: 1.05;
  margin-bottom: 0;
}

.hero-copy--top h1 .highlight {
  color: var(--rosewood);
}

.hero-copy--bottom {
  margin-top: 8px;
}

.hero-copy--bottom p {
  font-size: 17px;
  color: var(--ink-soft);
  margin-bottom: 28px;
  max-width: 460px;
  margin-left: auto;
  margin-right: auto;
}

.btn-hero {
  padding: 15px 40px;
  font-size: 14px;
}

.scroll-cue {
  display: block;
  margin-top: 40px;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--ink-soft);
  opacity: 0.7;
}

/* Spacer that preserves hero rhythm — the flower itself now
   renders in the fixed layer, not inside this box. */
.hero-stage {
  position: relative;
  z-index: 1;
  width: min(100%, 900px);
  height: min(58vh, 560px);
  margin: -10px 0;
}

.flower-podium {
  position: absolute;
  left: 50%;
  bottom: 4%;
  width: 60%;
  height: 60px;
  transform: translateX(-50%);
  background: radial-gradient(
    50% 100% at 50% 50%,
    rgba(233, 209, 200, 0.6) 0%,
    rgba(233, 209, 200, 0) 75%
  );
  filter: blur(2px);
  z-index: 1;
}

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

.reveal-hero {
  animation: heroIntro 1.1s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.hero-copy--bottom.reveal-hero {
  animation-delay: 0.15s;
}

@keyframes heroIntro {
  from {
    opacity: 0;
    transform: translateY(26px);
  }
  to {
    opacity: 1;
    transform: none;
  }
}

/* Clients Section */
.clients {
  position: relative;
  z-index: 10;
  padding: 90px 5%;
  text-align: center;
  background: var(--ivory-deep-glass);
  backdrop-filter: blur(10px) saturate(120%);
}

.clients h2 {
  font-size: 30px;
  margin-bottom: 12px;
}

.clients p {
  color: var(--ink-soft);
  margin-bottom: 52px;
  font-size: 15px;
}

.clients-grid {
  display: flex;
  justify-content: center;
  gap: 48px;
  flex-wrap: wrap;
  align-items: center;
}

.client-logo {
  width: 120px;
  height: 60px;
  background: var(--ivory);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c3bcb3;
  font-size: 12px;
  border: 1px solid var(--line);
  transition:
    transform 0.3s ease,
    box-shadow 0.3s ease;
}

.client-logo:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 24px rgba(28, 25, 23, 0.06);
}

/* Features Section */
.features {
  position: relative;
  z-index: 10;
  padding: 140px 5%;
  background: var(--ivory-glass);
  backdrop-filter: blur(10px) saturate(120%);
}

.features-header {
  text-align: center;
  max-width: 640px;
  margin: 0 auto 80px;
}

.features-header h2 {
  font-size: clamp(30px, 3.6vw, 42px);
  margin-bottom: 14px;
}

.features-header p {
  color: var(--ink-soft);
  font-size: 16px;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 32px;
  max-width: 1200px;
  margin: 0 auto;
}

.feature-card {
  text-align: left;
  padding: 44px 32px;
  border-radius: 20px;
  border: 1px solid var(--line);
  background: var(--ivory-glass);
  backdrop-filter: blur(6px);
  transition:
    transform 0.4s ease,
    box-shadow 0.4s ease,
    border-color 0.4s ease;
}

.feature-card:nth-child(2) {
  transform: translateY(-18px);
}

.feature-card:hover {
  transform: translateY(-8px);
  border-color: rgba(165, 88, 76, 0.25);
  box-shadow: 0 24px 48px rgba(28, 25, 23, 0.08);
}

.feature-card:nth-child(2):hover {
  transform: translateY(-26px);
}

.feature-icon {
  width: 64px;
  height: 64px;
  background: var(--ivory-deep);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 24px;
  font-size: 28px;
}

.feature-card h3 {
  font-size: 19px;
  font-weight: 700;
  margin-bottom: 12px;
}

.feature-card p {
  color: var(--ink-soft);
  font-size: 14.5px;
  line-height: 1.7;
}

/* Content Section */
.content-section {
  position: relative;
  z-index: 10;
  padding: 140px 5%;
  display: grid;
  grid-template-columns: 0.9fr 1.1fr;
  gap: 100px;
  align-items: center;
  background: transparent;
}

.content-section--reverse {
  grid-template-columns: 1.1fr 0.9fr;
}

.content-section--reverse .content-image {
  order: 2;
}

.content-section:nth-child(even) {
  background: var(--ivory-deep-glass);
  backdrop-filter: blur(10px) saturate(120%);
}

.content-image {
  height: 440px;
  background: var(--line);
  border-radius: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 12px;
  color: #b7afa6;
  overflow: hidden;
}

/* The flower now occupies the visual weight beside the text, so
   the original photo becomes a soft, translucent accent that the
   flower's glow can bleed around — element is preserved, not
   removed, just re-tuned for the new composition. */
.content-image--ghost {
  background: transparent;
  box-shadow: 0 30px 60px rgba(28, 25, 23, 0.1);
}

.content-image--ghost img {
  opacity: 0.82;
  border-radius: 20px;
}

.content-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.6s ease;
}

.content-image:hover img {
  transform: scale(1.04);
}

.content-text h2 {
  font-size: clamp(28px, 3.2vw, 38px);
  margin-bottom: 22px;
}

.content-text p {
  color: var(--ink-soft);
  font-size: 16px;
  line-height: 1.85;
  margin-bottom: 24px;
  max-width: 460px;
}

.btn-learn-more {
  padding: 14px 34px;
  background: var(--ink);
  color: var(--ivory);
  border: none;
  border-radius: 999px;
  font-family: "Poppins", sans-serif;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-learn-more:hover {
  background: var(--rosewood-dark);
  transform: translateY(-2px);
  box-shadow: 0 14px 28px rgba(127, 66, 56, 0.24);
}

/* Stats Section */
.stats {
  position: relative;
  z-index: 10;
  padding: 100px 5%;
  background: var(--espresso-glass);
  backdrop-filter: blur(10px);
  color: var(--ivory);
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 40px;
  max-width: 1200px;
  margin: 0 auto;
}

.stat-item {
  text-align: center;
  position: relative;
}

.stat-item:not(:last-child)::after {
  content: "";
  position: absolute;
  right: -20px;
  top: 10%;
  height: 80%;
  width: 1px;
  background: rgba(255, 255, 255, 0.1);
}

.stat-icon {
  font-size: 30px;
  margin-bottom: 14px;
  opacity: 0.9;
}

.stat-number {
  font-size: 34px;
  color: var(--ivory);
  margin-bottom: 6px;
}

.stat-label {
  color: rgba(248, 244, 239, 0.6);
  font-size: 13px;
  letter-spacing: 0.04em;
}

.highlight-vendor {
  animation: vendorGlow 2s ease-in-out;
}

@keyframes vendorGlow {
  0% {
    background-color: rgba(201, 166, 107, 0.25);
  }
  100% {
    background-color: transparent;
  }
}

/* Blog Section */
.blog {
  position: relative;
  z-index: 10;
  padding: 140px 5%;
  background: var(--ivory-glass);
  backdrop-filter: blur(10px) saturate(120%);
}

.blog-header {
  text-align: center;
  max-width: 560px;
  margin: 0 auto 64px;
}

.blog-header h2 {
  font-size: clamp(30px, 3.6vw, 40px);
  margin-bottom: 12px;
}

.blog-header p {
  color: var(--ink-soft);
  font-size: 16px;
}

.blog-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 32px;
  max-width: 1200px;
  margin: 0 auto;
}

.blog-card {
  background: var(--ivory-glass);
  backdrop-filter: blur(6px);
  border-radius: 18px;
  overflow: hidden;
  border: 1px solid var(--line);
  transition:
    transform 0.4s ease,
    box-shadow 0.4s ease,
    border-color 0.4s ease;
}

.blog-card:hover {
  transform: translateY(-6px);
  border-color: rgba(165, 88, 76, 0.25);
  box-shadow: 0 24px 48px rgba(28, 25, 23, 0.08);
}

.blog-image {
  width: 100%;
  height: 200px;
  background: var(--ivory-deep);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #b7afa6;
  font-size: 13px;
}

.blog-content {
  padding: 28px;
}

.blog-content h3 {
  font-size: 18px;
  font-weight: 700;
  margin-bottom: 14px;
}

.blog-link {
  color: var(--rosewood);
  font-size: 13.5px;
  font-weight: 500;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: gap 0.3s;
}

.blog-link:hover {
  gap: 8px;
}

.cta {
  position: relative;
  z-index: 10;
  padding: 140px 5%;
  text-align: center;
  background:
    radial-gradient(
      70% 120% at 50% 0%,
      rgba(197, 130, 118, 0.16) 0%,
      rgba(197, 130, 118, 0) 60%
    ),
    var(--ivory-deep-glass);
  backdrop-filter: blur(10px) saturate(120%);
  overflow: hidden;
}

.cta h2 {
  font-size: clamp(30px, 4.4vw, 46px);
  margin-bottom: 36px;
  max-width: 640px;
  margin-left: auto;
  margin-right: auto;
}

.btn-cta {
  padding: 18px 52px;
  background: var(--ink);
  color: var(--ivory);
  border: none;
  border-radius: 999px;
  font-family: "Poppins", sans-serif;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s ease;
  display: inline-block;
  text-decoration: none;
}

.btn-cta:hover {
  background: var(--rosewood-dark);
  transform: translateY(-3px);
  box-shadow: 0 20px 40px rgba(127, 66, 56, 0.28);
}

/* Footer */
.footer {
  position: relative;
  z-index: 10;
  background: var(--espresso);
  color: var(--ivory);
  padding: 80px 5% 32px;
}

.footer-content {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 60px;
  max-width: 1200px;
  margin: 0 auto 48px;
}

.footer-brand {
  display: flex;
  flex-direction: column;
  gap: 18px;
}

.footer-brand .logo {
  color: var(--ivory);
  font-size: 22px;
}

.footer-brand p {
  color: rgba(248, 244, 239, 0.55);
  font-size: 14px;
  line-height: 1.7;
  max-width: 320px;
}

.footer-section h4 {
  font-family: "Poppins", sans-serif;
  font-size: 13px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  margin-bottom: 20px;
  font-weight: 600;
  color: rgba(248, 244, 239, 0.9);
}

.footer-section ul {
  list-style: none;
}

.footer-section ul li {
  margin-bottom: 14px;
}

.footer-section ul li a {
  color: rgba(248, 244, 239, 0.6);
  text-decoration: none;
  font-size: 14px;
  transition: color 0.3s;
}

.footer-section ul li a:hover {
  color: var(--ivory);
}

.footer-bottom {
  text-align: center;
  padding-top: 32px;
  border-top: 1px solid rgba(248, 244, 239, 0.1);
  color: rgba(248, 244, 239, 0.45);
  font-size: 13px;
}

.social-links {
  display: flex;
  gap: 14px;
  margin-top: 6px;
}

.social-link {
  width: 36px;
  height: 36px;
  background: rgba(248, 244, 239, 0.08);
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

/* Responsive */
@media (max-width: 968px) {
  .nav-links {
    display: none;
  }

  .hero {
    padding: 140px 5% 60px;
  }

  .hero-copy--top h1 {
    font-size: 34px;
  }

  .hero-stage {
    height: 46vh;
  }

  .features-grid {
    grid-template-columns: 1fr;
  }

  .feature-card:nth-child(2),
  .feature-card:nth-child(2):hover {
    transform: none;
  }

  .content-section,
  .content-section--reverse {
    grid-template-columns: 1fr !important;
    gap: 40px;
    padding: 90px 5%;
  }

  .content-section--reverse .content-image {
    order: 1;
  }

  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
    row-gap: 40px;
  }

  .stat-item:nth-child(2)::after {
    display: none;
  }

  .blog-grid {
    grid-template-columns: 1fr;
  }

  .footer-content {
    grid-template-columns: 1fr;
    gap: 40px;
  }
}

@media (max-width: 640px) {
  .navbar {
    padding: 1rem 4%;
  }

  .navbar--scrolled {
    padding: 0.7rem 4%;
  }

  .hero {
    padding: 120px 4% 50px;
  }

  .hero-copy--top h1 {
    font-size: 26px;
  }

  .hero-copy--bottom p {
    font-size: 16px;
  }

  .hero-stage {
    height: 38vh;
  }

  .nav-buttons {
    gap: 8px;
  }

  .btn-login,
  .btn-register {
    padding: 8px 16px;
    font-size: 13px;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
