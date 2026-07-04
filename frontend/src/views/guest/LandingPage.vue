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
        <span>
          <img
            src="../../../public/bloomcraft-blankBg.png"
            alt="Bloomcraft Logo"
            width="50"
            height="50"
          />
        </span>
        <span>BloomCraft</span>
      </router-link>
      <div class="nav-links">
        <router-link to="/shop">Shop</router-link>
        <a href="#features" @click.prevent="scrollToSection('features')">Features</a>
        <a href="#how-it-works" @click.prevent="scrollToSection('how-it-works')">How It Works</a>
        <a href="#vendors" @click.prevent="scrollAndHighlight('register-vendor')">For Vendors</a>
        <a href="#blog" @click.prevent="scrollToSection('blog')">Blog</a>
      </div>
      <div class="nav-buttons">
        <template v-if="!isAuthenticated">
          <router-link to="/guest/login" class="btn-login">Login</router-link>
          <router-link to="/guest/register" class="btn-register">Register</router-link>
        </template>
      </div>
    </nav>

    <!-- Scroll Panels Story -->
    <div class="scroll-story-container">
      
      <!-- Panel 1: Hero Section -->
      <section class="panel-section hero-panel" ref="heroSection">
        <div class="panel-container">
          <div class="panel-content text-left-side">
            <span class="eyebrow reveal-fade-up">Bespoke florals, built by you</span>
            <h1 class="hero-title reveal-fade-up">
              Create your perfect
              <span class="highlight">bouquet</span>
            </h1>
            <p class="hero-desc reveal-fade-up">
              Where vendors meet creativity. Design custom flower arrangements in 3D
              or let our AI suggest the perfect bloom for every occasion.
            </p>
            <div class="hero-cta-wrap reveal-fade-up">
              <router-link to="/guest/register" class="btn-register btn-hero">
                Get Started
              </router-link>
            </div>
            <span class="scroll-cue reveal-fade-up">Scroll to explore</span>
          </div>
          <div class="panel-visual spacer-right" aria-hidden="true"></div>
        </div>
      </section>

      <!-- Panel 2: Clients Section -->
      <section class="panel-section clients-panel" ref="clientsSection">
        <div class="panel-container">
          <div class="panel-visual spacer-left" aria-hidden="true"></div>
          <div class="panel-content text-right-side">
            <h2 class="reveal-fade-up">Trusted by Flower Lovers</h2>
            <p class="reveal-fade-up">
              Join {{ stats.vendors }}+ vendors and thousands of happy customers
            </p>
            <div class="clients-grid">
              <div v-for="n in 5" :key="n" class="client-logo reveal-card">
                Logo {{ n }}
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Panel 3: Content Section 1 (3D Design Studio) -->
      <section class="panel-section content-panel" ref="contentSection1">
        <div class="panel-container">
          <div class="panel-content text-left-side">
            <span class="eyebrow reveal-fade-up">3D Design Studio</span>
            <h2 class="reveal-fade-up">Design in 3D, deliver with love</h2>
            <p class="reveal-fade-up">
              Our revolutionary 3D customization tool lets you become the designer.
              Choose flowers, arrange them in real-time, adjust colors and sizes,
              and visualize your perfect bouquet before placing your order.
            </p>
            <p class="reveal-fade-up">Every arrangement is unique, just like your story.</p>
            <div class="reveal-fade-up">
              <button class="btn-learn-more" @click="handleLearnMore('3d-designer')">
                Explore 3D Designer
              </button>
            </div>
          </div>
          <div class="panel-visual spacer-right" aria-hidden="true"></div>
        </div>
      </section>

      <!-- Panel 4: Features Section -->
      <section class="panel-section features-panel" id="features" ref="featuresSection">
        <div class="panel-container">
          <div class="panel-visual spacer-left" aria-hidden="true"></div>
          <div class="panel-content text-right-side">
            <span class="eyebrow reveal-fade-up">Why BloomCraft</span>
            <h2 class="reveal-fade-up">Everything you need to bloom</h2>
            <p class="reveal-fade-up features-header-desc">
              Powerful features for vendors and delightful experiences for customers
            </p>
            <div class="features-stacked-grid">
              <div
                v-for="feature in features"
                :key="feature.id"
                class="feature-card-item reveal-card"
              >
                <div class="feature-icon">{{ feature.icon }}</div>
                <div class="feature-info">
                  <h3>{{ feature.title }}</h3>
                  <p>{{ feature.description }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Panel 5: Content Section 2 (AI Concierge) -->
      <section class="panel-section content-panel" id="how-it-works" ref="contentSection2">
        <div class="panel-container">
          <div class="panel-content text-left-side">
            <span class="eyebrow reveal-fade-up">AI Concierge</span>
            <h2 class="reveal-fade-up">AI-powered recommendations</h2>
            <p class="reveal-fade-up">
              Don't know where to start? Our intelligent AI analyzes the occasion,
              season, recipient preferences, and current trends to suggest the
              perfect arrangement.
            </p>
            <p class="reveal-fade-up">
              Get inspired by thousands of beautiful combinations, or let our AI
              create something uniquely yours.
            </p>
            <div class="reveal-fade-up">
              <button class="btn-learn-more" @click="handleLearnMore('ai-designer')">
                Try AI Designer
              </button>
            </div>
          </div>
          <div class="panel-visual spacer-right" aria-hidden="true"></div>
        </div>
      </section>

      <!-- Panel 6: Stats Section -->
      <section class="panel-section stats-panel" id="vendors" ref="statsSection">
        <div class="panel-container">
          <div class="panel-visual spacer-left" aria-hidden="true"></div>
          <div class="panel-content text-right-side">
            <div class="stats-stacked-grid">
              <div
                v-for="stat in statsData"
                :key="stat.label"
                class="stat-card-item reveal-card"
              >
                <div class="stat-icon">{{ stat.icon }}</div>
                <div class="stat-number">{{ stat.number }}</div>
                <div class="stat-label">{{ stat.label }}</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Panel 7: Blog Section -->
      <section class="panel-section blog-panel" id="blog" ref="blogSection">
        <div class="panel-container">
          <div class="panel-content text-left-side">
            <span class="eyebrow reveal-fade-up">The Journal</span>
            <h2 class="reveal-fade-up">Fresh insights from our garden</h2>
            <p class="reveal-fade-up blog-header-desc">
              Tips, trends, and stories from the world of flowers
            </p>
            <div class="blog-stacked-grid">
              <div v-for="post in blogPosts" :key="post.id" class="blog-card-item reveal-card">
                <div class="blog-image">Blog Image {{ post.id }}<br />400x250px</div>
                <div class="blog-content">
                  <h3>{{ post.title }}</h3>
                  <a href="#" @click.prevent="readBlog(post.id)" class="blog-link">
                    Read more →
                  </a>
                </div>
              </div>
            </div>
          </div>
          <div class="panel-visual spacer-right" aria-hidden="true"></div>
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

    </div>

    <!-- Footer -->
    <footer class="footer" ref="footerSection">
      <div class="footer-content">
        <div class="footer-brand">
          <div class="logo">
            <span>
              <img
                src="../../../public/bloomcraft-darkmode-removebg.png"
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
              <router-link to="/guest/vendor_register">Become a Vendor</router-link>
            </li>
            <li>
              <a href="#" @click.prevent="handleFooterLink('#pricing')">Pricing</a>
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

// Auth check placeholder (preserved and declared to prevent template warning)
const isAuthenticated = ref(false);

// Panel section refs
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

// Methods (unchanged)
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
// pointer interaction.
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
    roughness: 0.45,
    metalness: 0.1,
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
      
      petal.castShadow = true;
      petal.receiveShadow = true;

      pivot.add(petal);
      ringGroup.add(pivot);
    }

    group.add(ringGroup);
    rings.push(ringGroup);
  });

  const center = new THREE.Mesh(
    new THREE.SphereGeometry(0.3, 24, 24),
    new THREE.MeshStandardMaterial({ color: 0xf2c879, roughness: 0.5, metalness: 0.1 }),
  );
  center.castShadow = true;
  center.receiveShadow = true;
  group.add(center);

  const leafMaterial = new THREE.MeshStandardMaterial({
    color: 0x8fae82,
    roughness: 0.5,
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
    
    leaf.castShadow = true;
    leaf.receiveShadow = true;

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
  const texture = radialTexture("rgba(30,20,18,0.25)", "rgba(30,20,18,0)");
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
  const texture = radialTexture("rgba(255,225,210,0.8)", "rgba(255,225,210,0)");
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
  camera.position.set(0, 0, 6);

  renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
  renderer.setSize(width, height);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 1.15;
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;

  scene.add(new THREE.AmbientLight(0xfff2ec, 0.7));

  const key = new THREE.DirectionalLight(0xffffff, 1.4);
  key.position.set(3, 4, 5);
  key.castShadow = true;
  key.shadow.mapSize.width = 1024;
  key.shadow.mapSize.height = 1024;
  key.shadow.camera.near = 0.5;
  key.shadow.camera.far = 15;
  key.shadow.camera.left = -3;
  key.shadow.camera.right = 3;
  key.shadow.camera.top = 3;
  key.shadow.camera.bottom = -3;
  key.shadow.bias = -0.0005;
  scene.add(key);

  const rimLight = new THREE.PointLight(0xffd9c9, 0.8, 10);
  rimLight.position.set(-3, 1, -2);
  scene.add(rimLight);

  scene.add(buildBloomGlow());
  scene.add(buildGroundShadow());

  const built = buildFlower();
  flowerGroup = built.group;
  flowerRings = built.rings;

  rig = new THREE.Group();
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
    // Gentle floating
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
    } else {
      // Gentle spin response during scroll
      flowerGroup.rotation.y += 0.005;
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

// Calculate the precise X coordinate for aligning with left/right column
function getSpreadX() {
  if (!camera) return 1.5;
  const vw = window.innerWidth;
  if (vw < 968) return 0; // Stack layout: center
  const aspect = vw / window.innerHeight;
  const halfVisibleWidth = Math.tan((camera.fov * Math.PI) / 360) * 6 * aspect;
  return halfVisibleWidth * 0.42; // Perfect horizontal column alignment
}

function setupFlowerJourney() {
  if (!rig || !camera || prefersReducedMotion) return;

  // Clear existing triggers to make it perfectly resize-proof
  journeyTriggers.forEach((st) => st && st.kill());
  journeyTriggers.length = 0;

  const spread = getSpreadX();
  const isMobile = window.innerWidth < 968;
  const scaleBase = isMobile ? 0.7 : 1.15;

  // Setup scroll behavior to record scrolling state
  const scrollUpdateTrigger = ScrollTrigger.create({
    trigger: ".scroll-story-container",
    start: "top top",
    end: "bottom bottom",
    onUpdate: () => {
      isScrolling = true;
      clearTimeout(scrollIdleTimeout);
      scrollIdleTimeout = setTimeout(() => {
        isScrolling = false;
      }, 150);
    }
  });
  journeyTriggers.push(scrollUpdateTrigger);

  // Set initial Hero Section position
  gsap.set(rig.position, { x: spread, y: isMobile ? -0.2 : 0, z: 0 });
  gsap.set(rig.scale, { x: scaleBase, y: scaleBase, z: scaleBase });
  gsap.set(rig.rotation, { x: 0, y: Math.PI * 0.15, z: 0 });
  if (flowerCanvas.value) {
    gsap.set(flowerCanvas.value, { opacity: 1 });
  }

  // Helper for adding timeline scrolls
  const addScrollJourney = (trigger, toX, toRotY, toScaleMul = 1.0) => {
    if (!trigger) return;
    const tl = gsap.timeline({
      scrollTrigger: {
        trigger,
        start: "top 90%",
        end: "top 20%",
        scrub: 1.0,
      }
    });

    tl.to(rig.position, { x: toX, ease: "power2.inOut" }, 0);
    tl.to(rig.rotation, { y: toRotY, ease: "power2.inOut" }, 0);
    tl.to(rig.scale, { 
      x: scaleBase * toScaleMul, 
      y: scaleBase * toScaleMul, 
      z: scaleBase * toScaleMul, 
      ease: "power2.inOut" 
    }, 0);

    journeyTriggers.push(tl.scrollTrigger);
  };

  // Journey Steps mapping: alternates left/right side columns
  // Panel 2: Clients (Left)
  addScrollJourney(clientsSection.value, -spread, Math.PI * 0.6, 0.9);
  
  // Panel 3: Content Section 1 (Right)
  addScrollJourney(contentSection1.value, spread, Math.PI * 1.1, 1.05);

  // Panel 4: Features Section (Left)
  addScrollJourney(featuresSection.value, -spread, Math.PI * 1.6, 0.95);

  // Panel 5: Content Section 2 (Right)
  addScrollJourney(contentSection2.value, spread, Math.PI * 2.1, 1.05);

  // Panel 6: Stats Section (Left)
  addScrollJourney(statsSection.value, -spread, Math.PI * 2.6, 0.9);

  // Panel 7: Blog Section (Right)
  addScrollJourney(blogSection.value, spread, Math.PI * 3.1, 1.05);

  // Panel 8: CTA Section (Center / Showcased)
  if (ctaSection.value) {
    const tl = gsap.timeline({
      scrollTrigger: {
        trigger: ctaSection.value,
        start: "top 90%",
        end: "top 25%",
        scrub: 1.0,
      }
    });
    tl.to(rig.position, { x: 0, y: isMobile ? 0.35 : 0.45, ease: "power2.inOut" }, 0);
    tl.to(rig.rotation, { y: Math.PI * 3.75, ease: "power2.inOut" }, 0);
    tl.to(rig.scale, { 
      x: scaleBase * 1.35, 
      y: scaleBase * 1.35, 
      z: scaleBase * 1.35, 
      ease: "power2.inOut" 
    }, 0);
    journeyTriggers.push(tl.scrollTrigger);
  }

  // Footer Section: Fades out
  if (footerSection.value && flowerCanvas.value) {
    const tl = gsap.timeline({
      scrollTrigger: {
        trigger: footerSection.value,
        start: "top 95%",
        end: "top 45%",
        scrub: 1.0,
      }
    });
    tl.to(flowerCanvas.value, { opacity: 0, ease: "none" }, 0);
    journeyTriggers.push(tl.scrollTrigger);
  }
}

function setupSectionReveals() {
  const panels = gsap.utils.toArray('.panel-section');
  
  panels.forEach((panel) => {
    // Fade in + slide up header details
    const revealElements = panel.querySelectorAll('.reveal-fade-up');
    if (revealElements.length) {
      gsap.fromTo(
        revealElements,
        { autoAlpha: 0, y: 35 },
        {
          autoAlpha: 1,
          y: 0,
          duration: 0.95,
          ease: "power3.out",
          stagger: 0.12,
          scrollTrigger: {
            trigger: panel,
            start: "top 78%",
            toggleActions: "play none none reverse",
          }
        }
      );
    }

    // Stagger child cards/items
    const cards = panel.querySelectorAll('.reveal-card');
    if (cards.length) {
      gsap.fromTo(
        cards,
        { autoAlpha: 0, y: 45, scale: 0.96 },
        {
          autoAlpha: 1,
          y: 0,
          scale: 1,
          duration: 0.85,
          ease: "power2.out",
          stagger: 0.08,
          scrollTrigger: {
            trigger: panel,
            start: "top 72%",
            toggleActions: "play none none reverse",
          }
        }
      );
    }
  });
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

  // Recalculate GSAP coordinates dynamically
  setupFlowerJourney();
  ScrollTrigger.refresh();
}

function initSmoothScroll() {
  lenis = new Lenis({
    duration: 1.2,
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
  setupFlowerJourney();
  setupSectionReveals();
  setupBackgroundParallax();
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
  --ivory: #faf8f5;
  --ivory-deep: #f2ede7;
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
  font-family: "Poppins", -apple-system, sans-serif;
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

.eyebrow {
  display: inline-block;
  font-family: "Poppins", sans-serif;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--rosewood);
  margin-bottom: 16px;
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

/* Navigation */
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  background: rgba(250, 248, 245, 0.7);
  backdrop-filter: blur(16px);
  padding: 1.6rem 6%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 1000;
  border-bottom: 1px solid transparent;
  transition:
    padding 0.4s cubic-bezier(0.16, 1, 0.3, 1),
    background-color 0.4s cubic-bezier(0.16, 1, 0.3, 1),
    border-bottom-color 0.4s cubic-bezier(0.16, 1, 0.3, 1),
    box-shadow 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.navbar--scrolled {
  padding: 0.95rem 6%;
  background: rgba(250, 248, 245, 0.9);
  border-bottom-color: var(--line);
  box-shadow: 0 8px 30px rgba(30, 27, 24, 0.04);
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
  gap: 44px;
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
  gap: 16px;
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
  padding-top: 180px;
}

.hero-title {
  font-size: clamp(44px, 5.8vw, 78px);
  line-height: 1.08;
  margin-bottom: 24px;
  color: var(--ink);
}

.hero-title .highlight {
  color: var(--rosewood);
  background: linear-gradient(120deg, var(--rosewood) 30%, var(--gold) 90%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.hero-desc {
  font-size: 18px;
  color: var(--ink-soft);
  margin-bottom: 36px;
  max-width: 520px;
  line-height: 1.8;
}

.btn-hero {
  padding: 16px 44px;
  font-size: 15px;
}

.scroll-cue {
  display: block;
  margin-top: 48px;
  font-size: 11.5px;
  font-weight: 600;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: var(--ink-soft);
  opacity: 0.65;
  animation: pulseCue 2s infinite ease-in-out;
}

@keyframes pulseCue {
  0%, 100% {
    transform: translateY(0);
    opacity: 0.45;
  }
  50% {
    transform: translateY(6px);
    opacity: 0.8;
  }
}

/* Clients Panel */
.clients-panel h2 {
  font-size: clamp(28px, 3.2vw, 38px);
  margin-bottom: 12px;
}

.clients-panel p {
  color: var(--ink-soft);
  margin-bottom: 40px;
  font-size: 16px;
}

.clients-grid {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  align-items: center;
}

.client-logo {
  padding: 14px 24px;
  background: rgba(250, 248, 245, 0.5);
  border-radius: 12px;
  color: var(--ink-soft);
  font-size: 13px;
  font-weight: 500;
  border: 1px solid var(--line);
  transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
  backdrop-filter: blur(6px);
}

.client-logo:hover {
  background: rgba(250, 248, 245, 0.85);
  border-color: var(--rosewood);
  transform: translateY(-4px);
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.03);
}

/* Content Panels */
.content-panel h2 {
  font-size: clamp(30px, 3.5vw, 40px);
  margin-bottom: 24px;
  line-height: 1.2;
}

.content-panel p {
  color: var(--ink-soft);
  font-size: 16.5px;
  line-height: 1.85;
  margin-bottom: 28px;
  max-width: 500px;
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

/* Features Panel */
.features-panel h2 {
  font-size: clamp(30px, 3.5vw, 40px);
  margin-bottom: 14px;
}

.features-header-desc {
  color: var(--ink-soft);
  font-size: 16.5px;
  margin-bottom: 48px;
}

.features-stacked-grid {
  display: flex;
  flex-direction: column;
  gap: 24px;
  width: 100%;
}

.feature-card-item {
  display: flex;
  gap: 24px;
  padding: 28px;
  border-radius: 20px;
  border: 1px solid var(--line);
  background: rgba(250, 248, 245, 0.5);
  backdrop-filter: blur(8px);
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.feature-card-item:hover {
  border-color: rgba(184, 101, 88, 0.25);
  transform: translateY(-4px) scale(1.01);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04);
  background: rgba(250, 248, 245, 0.8);
}

.feature-icon {
  width: 58px;
  height: 58px;
  background: var(--ivory-deep);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 26px;
  flex-shrink: 0;
}

.feature-info {
  text-align: left;
}

.feature-info h3 {
  font-size: 18.5px;
  margin-bottom: 8px;
}

.feature-info p {
  color: var(--ink-soft);
  font-size: 14.5px;
  line-height: 1.7;
}

/* Stats Panel */
.stats-stacked-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 28px;
}

.stat-card-item {
  background: rgba(250, 248, 245, 0.5);
  backdrop-filter: blur(8px);
  border: 1px solid var(--line);
  border-radius: 20px;
  padding: 34px 28px;
  text-align: center;
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.stat-card-item:hover {
  transform: translateY(-4px);
  border-color: rgba(184, 101, 88, 0.25);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04);
  background: rgba(250, 248, 245, 0.8);
}

.stat-icon {
  font-size: 32px;
  margin-bottom: 12px;
}

.stat-number {
  font-size: 36px;
  color: var(--ink);
  margin-bottom: 6px;
}

.stat-label {
  color: var(--ink-soft);
  font-size: 14px;
  letter-spacing: 0.04em;
  font-weight: 500;
}

.highlight-vendor {
  animation: vendorGlow 2s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes vendorGlow {
  0% {
    background-color: rgba(212, 178, 122, 0.25);
    border-color: var(--gold);
  }
  100% {
    background-color: rgba(250, 248, 245, 0.5);
    border-color: var(--line);
  }
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
  background: rgba(250, 248, 245, 0.5);
  backdrop-filter: blur(8px);
  border-radius: 20px;
  overflow: hidden;
  border: 1px solid var(--line);
  display: flex;
  transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.blog-card-item:hover {
  transform: translateY(-4px);
  border-color: rgba(184, 101, 88, 0.25);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04);
  background: rgba(250, 248, 245, 0.8);
}

.blog-image {
  width: 180px;
  background: var(--ivory-deep);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #b7afa6;
  font-size: 12px;
  text-align: center;
  padding: 20px;
  flex-shrink: 0;
  border-right: 1px solid var(--line);
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
  color: var(--rosewood);
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

/* CTA Panel */
.cta-panel {
  display: flex;
  justify-content: center;
  align-items: center;
  text-align: center;
  padding: 160px 6%;
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
  .navbar {
    padding: 1.2rem 5%;
  }

  .navbar--scrolled {
    padding: 0.85rem 5%;
  }

  .nav-links {
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

  .clients-grid {
    justify-content: center;
  }

  .features-stacked-grid,
  .blog-stacked-grid {
    gap: 20px;
  }

  .feature-card-item,
  .blog-card-item {
    background: rgba(250, 248, 245, 0.4);
    padding: 20px;
  }

  .blog-card-item {
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
    gap: 20px;
  }

  .stat-card-item {
    padding: 24px 18px;
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
    padding: 0.75rem 4%;
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
}
</style>
