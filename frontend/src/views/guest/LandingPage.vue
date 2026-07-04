<template>
  <div class="landing-page">
    <!-- Navigation -->
    <nav class="navbar">
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
      <div class="hero-content">
        <h1>
          Create your perfect bouquet
          <span class="highlight">with AI & 3D customization</span>
        </h1>
        <p>
          Where vendors meet creativity. Design custom flower arrangements in 3D
          or let our AI suggest the perfect bloom for every occasion.
        </p>
        <router-link to="/guest/register" class="btn-register"
          >Get Started</router-link
        >
      </div>
      <div class="hero-image">
        <div class="flower-podium" aria-hidden="true"></div>
        <span class="drift-petal petal-a" aria-hidden="true"></span>
        <span class="drift-petal petal-b" aria-hidden="true"></span>
        <span class="drift-petal petal-c" aria-hidden="true"></span>
        <canvas ref="flowerCanvas" class="flower-canvas"></canvas>
      </div>
    </section>

    <!-- Clients/Partners Section -->
    <section class="clients">
      <h2>Trusted by Flower Lovers</h2>
      <p>Join {{ stats.vendors }}+ vendors and thousands of happy customers</p>
      <div class="clients-grid">
        <div v-for="n in 5" :key="n" class="client-logo">Logo {{ n }}</div>
      </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features">
      <div class="features-header">
        <h2>Everything you need to bloom</h2>
        <p>
          Powerful features for vendors and delightful experiences for customers
        </p>
      </div>
      <div class="features-grid">
        <div v-for="feature in features" :key="feature.id" class="feature-card">
          <div class="feature-icon">{{ feature.icon }}</div>
          <h3>{{ feature.title }}</h3>
          <p>{{ feature.description }}</p>
        </div>
      </div>
    </section>

    <!-- Content Section 1 -->
    <section class="content-section">
      <div class="content-text">
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
      <div class="content-image">
        <img
          src="../../../public/3d flower.png"
          alt="Bloomcraft Logo"
          width="800"
          height="500"
        />
      </div>
    </section>

    <!-- Stats Section -->
    <section id="vendors" class="stats">
      <div class="stats-grid">
        <div v-for="stat in statsData" :key="stat.label" class="stat-item">
          <div class="stat-icon">{{ stat.icon }}</div>
          <div class="stat-number">{{ stat.number }}</div>
          <div class="stat-label">{{ stat.label }}</div>
        </div>
      </div>
    </section>

    <!-- Content Section 2 -->
    <section id="how-it-works" class="content-section">
      <div class="content-text">
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
      <div class="content-image">
        <img
          src="../../../public/ai power.jpg"
          alt="Bloomcraft Logo"
          width="800"
          height="500"
        />
      </div>
    </section>

    <!-- Blog Section -->
    <section id="blog" class="blog">
      <div class="blog-header">
        <h2>Fresh insights from our garden</h2>
        <p>Tips, trends, and stories from the world of flowers</p>
      </div>
      <div class="blog-grid">
        <div v-for="post in blogPosts" :key="post.id" class="blog-card">
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
    <section class="cta">
      <h2>Ready to create something beautiful?</h2>
      <router-link to="/guest/register" class="btn-cta"
        >Start Designing Now</router-link
      >
    </section>

    <!-- Footer -->
    <footer class="footer">
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

// ---- Hero 3D flower refs ----
const heroSection = ref(null);
const flowerCanvas = ref(null);

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

// Methods
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
  // You can add navigation or modal logic here
  router.push("/guest/register");
};

const readBlog = (postId) => {
  console.log("Reading blog post:", postId);
};

const handleFooterLink = (url) => {
  console.log("Footer link clicked:", url);
};

// ==========================================================
// Hero 3D flower: scene, scroll animation, pointer interaction
// (Hero section visuals/animations only — no routes, links,
// or structure elsewhere on the page are affected.)
// ==========================================================
let renderer = null;
let scene = null;
let camera = null;
let rig = null; // controlled by scroll (position.x, rotation.y)
let flowerGroup = null; // controlled by pointer tilt + idle bob
let particles = null;
let rafId = null;
let lenis = null;
let lenisRafId = null;
let scrollTween = null;

let isScrolling = false;
let scrollIdleTimeout = null;
const pointer = { x: 0, y: 0 };
const tilt = { x: 0, z: 0 };

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

  const rings = [
    { count: 6, spin: 0.0, tilt: 0.3, length: 0.9, color: 0xf7ddd4 },
    { count: 8, spin: 0.35, tilt: 0.7, length: 1.25, color: 0xefbdb3 },
    { count: 10, spin: 0.18, tilt: 1.1, length: 1.55, color: 0xe4988f },
  ];

  rings.forEach(({ count, spin, tilt: ringTilt, length, color }) => {
    const material = petalMaterial(color);
    const petalGeo = new THREE.SphereGeometry(1, 20, 20);

    for (let i = 0; i < count; i++) {
      const pivot = new THREE.Object3D();
      pivot.rotation.y = (i / count) * Math.PI * 2 + spin;

      const petal = new THREE.Mesh(petalGeo, material);
      petal.scale.set(0.42, length, 0.1);
      petal.position.set(0, length * 0.42, 0);
      petal.rotation.x = ringTilt;

      pivot.add(petal);
      group.add(pivot);
    }
  });

  // Flower center
  const center = new THREE.Mesh(
    new THREE.SphereGeometry(0.3, 24, 24),
    new THREE.MeshStandardMaterial({ color: 0xf2c879, roughness: 0.6 }),
  );
  group.add(center);

  // Leaves
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

  group.scale.setScalar(1.1);
  return group;
}

function buildParticles() {
  const count = 50;
  const positions = new Float32Array(count * 3);
  for (let i = 0; i < count; i++) {
    positions[i * 3] = (Math.random() - 0.5) * 6;
    positions[i * 3 + 1] = (Math.random() - 0.5) * 5;
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

  const width = canvas.clientWidth || 1;
  const height = canvas.clientHeight || 1;

  scene = new THREE.Scene();
  camera = new THREE.PerspectiveCamera(38, width / height, 0.1, 100);
  camera.position.set(0, 0.4, 6);

  renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
  renderer.setSize(width, height);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));

  scene.add(new THREE.AmbientLight(0xfff2ec, 0.9));

  const key = new THREE.DirectionalLight(0xffffff, 1.1);
  key.position.set(3, 4, 5);
  scene.add(key);

  const rimLight = new THREE.PointLight(0xffd9c9, 0.8, 10);
  rimLight.position.set(-3, 1, -2);
  scene.add(rimLight);

  flowerGroup = buildFlower();

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
    flowerGroup.position.y = Math.sin(Date.now() * 0.0006) * 0.08;

    if (!isScrolling) {
      tilt.x += (pointer.y * 0.22 - tilt.x) * 0.04;
      tilt.z += (pointer.x * -0.18 - tilt.z) * 0.04;
      flowerGroup.rotation.x = tilt.x;
      flowerGroup.rotation.z = tilt.z;
    }
  }

  if (particles) {
    particles.rotation.y += 0.0006;
  }

  renderer.render(scene, camera);
}

function setupScrollAnimation() {
  if (!heroSection.value || !rig || !camera) return;

  scrollTween = gsap.timeline({
    scrollTrigger: {
      trigger: heroSection.value,
      start: "top top",
      end: "bottom top",
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

  scrollTween
    .to(rig.position, { x: 1.5, ease: "none" }, 0)
    .to(rig.rotation, { y: Math.PI * 0.65, ease: "none" }, 0)
    .to(camera.position, { z: 5.1, ease: "none" }, 0);
}

function handlePointerMove(event) {
  const canvas = flowerCanvas.value;
  if (!canvas) return;
  const rect = canvas.getBoundingClientRect();
  pointer.x = ((event.clientX - rect.left) / rect.width) * 2 - 1;
  pointer.y = ((event.clientY - rect.top) / rect.height) * 2 - 1;
}

function handleResize() {
  const canvas = flowerCanvas.value;
  if (!canvas || !renderer || !camera) return;
  const width = canvas.clientWidth || 1;
  const height = canvas.clientHeight || 1;
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
  setupScrollAnimation();
  initSmoothScroll();
  window.addEventListener("mousemove", handlePointerMove, { passive: true });
  window.addEventListener("resize", handleResize);
});

onBeforeUnmount(() => {
  window.removeEventListener("mousemove", handlePointerMove);
  window.removeEventListener("resize", handleResize);
  clearTimeout(scrollIdleTimeout);

  if (rafId) cancelAnimationFrame(rafId);
  if (lenisRafId) cancelAnimationFrame(lenisRafId);
  if (lenis) lenis.destroy();
  if (scrollTween && scrollTween.scrollTrigger)
    scrollTween.scrollTrigger.kill();
  ScrollTrigger.getAll().forEach((trigger) => trigger.kill());
  if (renderer) renderer.dispose();
});
</script>

<style scoped>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family:
    -apple-system, BlinkMacSystemFont, "Segoe UI", "Roboto", "Oxygen", "Ubuntu",
    "Cantarell", sans-serif;
  color: #2d3748;
  line-height: 1.6;
  overflow-x: hidden;
}

/* Navigation */
.navbar {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  padding: 1rem 5%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  z-index: 1000;
  box-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
}

.logo {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 24px;
  font-weight: 600;
  color: #2d3748;
  text-decoration: none;
}

.nav-links {
  display: flex;
  gap: 32px;
  align-items: center;
}

.nav-links a {
  color: #4a5568;
  text-decoration: none;
  font-size: 15px;
  transition: color 0.3s;
}

.nav-links a:hover {
  color: #2d3748;
}

.nav-buttons {
  display: flex;
  gap: 12px;
}

.btn-login {
  padding: 10px 24px;
  background: transparent;
  color: #2d3748;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  text-decoration: none;
  display: inline-block;
}

.btn-login:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

.btn-register {
  padding: 10px 24px;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
  text-decoration: none;
  display: inline-block;
}

.btn-register:hover {
  background: #1a202c;
  transform: translateY(-1px);
}

/* Hero Section */
@import url("https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600&display=swap");

.hero {
  position: relative;
  margin-top: 80px;
  padding: 80px 5% 60px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 60px;
  align-items: center;
  min-height: calc(100vh - 80px);
  overflow: hidden;
}

.hero-glow {
  position: absolute;
  inset: -10% -10% -10% -10%;
  background:
    radial-gradient(
      60% 55% at 78% 45%,
      rgba(244, 197, 187, 0.35) 0%,
      rgba(244, 197, 187, 0) 70%
    ),
    radial-gradient(
      40% 40% at 8% 15%,
      rgba(143, 174, 130, 0.12) 0%,
      rgba(143, 174, 130, 0) 70%
    );
  pointer-events: none;
  z-index: 0;
}

.hero-content {
  position: relative;
  z-index: 1;
}

.hero-content h1 {
  font-family: "Playfair Display", Georgia, serif;
  font-size: 50px;
  font-weight: 500;
  line-height: 1.22;
  letter-spacing: -0.01em;
  margin-bottom: 20px;
}

.hero-content h1 .highlight {
  color: #c97b6f;
  font-weight: 600;
}

.hero-content p {
  font-size: 18px;
  color: #718096;
  margin-bottom: 32px;
  max-width: 500px;
}

.hero-image {
  position: relative;
  z-index: 1;
  height: 500px;
  background: transparent;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.flower-canvas {
  position: relative;
  z-index: 2;
  width: 100%;
  height: 100%;
  display: block;
  cursor: grab;
}

.flower-podium {
  position: absolute;
  left: 50%;
  bottom: 6%;
  width: 68%;
  height: 60px;
  transform: translateX(-50%);
  background: radial-gradient(
    50% 100% at 50% 50%,
    rgba(233, 209, 200, 0.55) 0%,
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
  animation: petalDrift 9s ease-in-out infinite;
  pointer-events: none;
}

.petal-a {
  top: 12%;
  left: 8%;
  animation-delay: 0s;
}

.petal-b {
  top: 60%;
  right: 12%;
  animation-delay: 2.4s;
  transform: rotate(30deg);
}

.petal-c {
  top: 30%;
  right: 30%;
  animation-delay: 4.8s;
  transform: rotate(-20deg);
}

@keyframes petalDrift {
  0% {
    transform: translate(0, 0) rotate(0deg);
    opacity: 0.15;
  }
  20% {
    opacity: 0.7;
  }
  50% {
    transform: translate(-12px, 22px) rotate(20deg);
  }
  80% {
    opacity: 0.4;
  }
  100% {
    transform: translate(6px, 48px) rotate(45deg);
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .drift-petal {
    animation: none;
    opacity: 0.3;
  }
}

/* Clients Section */
.clients {
  padding: 60px 5%;
  text-align: center;
  background: #f7fafc;
}

.clients h2 {
  font-size: 32px;
  font-weight: 400;
  margin-bottom: 12px;
}

.clients p {
  color: #718096;
  margin-bottom: 48px;
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
  background: white;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #cbd5e0;
  font-size: 12px;
  border: 1px solid #e2e8f0;
}

/* Features Section */
.features {
  padding: 80px 5%;
}

.features-header {
  text-align: center;
  margin-bottom: 60px;
}

.features-header h2 {
  font-size: 36px;
  font-weight: 400;
  margin-bottom: 12px;
}

.features-header p {
  color: #718096;
  font-size: 16px;
}

.features-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 40px;
  max-width: 1200px;
  margin: 0 auto;
}

.feature-card {
  text-align: center;
  padding: 32px;
}

.feature-icon {
  width: 80px;
  height: 80px;
  background: #f0fff4;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 24px;
  font-size: 32px;
}

.feature-card h3 {
  font-size: 20px;
  font-weight: 500;
  margin-bottom: 12px;
}

.feature-card p {
  color: #718096;
  font-size: 15px;
  line-height: 1.6;
}

/* Content Section */
.content-section {
  padding: 80px 5%;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 80px;
  align-items: center;
}

.content-section:nth-child(even) {
  background: #f7fafc;
}

.content-section:nth-child(even) .content-image {
  order: 2;
}

.content-image {
  height: 400px;
  background: #e2e8f0;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 12px;
  color: #a0aec0;
}

/* .content-image::before {
  content: "📸 Content Image";
  font-size: 16px;
}

.content-image::after {
  content: "800 x 600px";
  font-size: 13px;
  color: #cbd5e0;
} */

.content-text h2 {
  font-size: 32px;
  font-weight: 400;
  margin-bottom: 20px;
}

.content-text p {
  color: #718096;
  font-size: 16px;
  line-height: 1.8;
  margin-bottom: 24px;
}

.btn-learn-more {
  padding: 12px 32px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-learn-more:hover {
  background: #38a169;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
}

/* Stats Section */
.stats {
  padding: 60px 5%;
  background: #f0fff4;
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
}

.stat-icon {
  font-size: 32px;
  margin-bottom: 12px;
}

.stat-number {
  font-size: 32px;
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 4px;
}

.stat-label {
  color: #718096;
  font-size: 14px;
}

.highlight-vendor {
  animation: vendorGlow 2s ease-in-out;
}

@keyframes vendorGlow {
  0% {
    background-color: rgba(255, 255, 255, 0.4);
  }
  100% {
    background-color: transparent;
  }
}

/* Blog Section */
.blog {
  padding: 80px 5%;
}

.blog-header {
  text-align: center;
  margin-bottom: 60px;
}

.blog-header h2 {
  font-size: 36px;
  font-weight: 400;
  margin-bottom: 12px;
}

.blog-header p {
  color: #718096;
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
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
  transition: all 0.3s;
}

.blog-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.blog-image {
  width: 100%;
  height: 200px;
  background: #e2e8f0;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #a0aec0;
  font-size: 14px;
}

.blog-content {
  padding: 24px;
}

.blog-content h3 {
  font-size: 18px;
  font-weight: 500;
  margin-bottom: 12px;
}

.blog-link {
  color: #48bb78;
  font-size: 14px;
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
  padding: 80px 5%;
  text-align: center;
  background: linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%);
}

.cta h2 {
  font-size: 40px;
  font-weight: 400;
  margin-bottom: 32px;
}

.btn-cta {
  padding: 16px 48px;
  background: #48bb78;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 16px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-cta:hover {
  background: #38a169;
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(72, 187, 120, 0.4);
}

/* Footer */
.footer {
  background: #2d3748;
  color: white;
  padding: 60px 5% 32px;
}

.footer-content {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1fr;
  gap: 60px;
  max-width: 1200px;
  margin: 0 auto 40px;
}

.footer-brand {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.footer-brand .logo {
  color: white;
  font-size: 24px;
}

.footer-brand p {
  color: #cbd5e0;
  font-size: 14px;
  line-height: 1.6;
}

.footer-section h4 {
  font-size: 16px;
  margin-bottom: 16px;
  font-weight: 500;
}

.footer-section ul {
  list-style: none;
}

.footer-section ul li {
  margin-bottom: 12px;
}

.footer-section ul li a {
  color: #cbd5e0;
  text-decoration: none;
  font-size: 14px;
  transition: color 0.3s;
}

.footer-section ul li a:hover {
  color: white;
}

.footer-bottom {
  text-align: center;
  padding-top: 32px;
  border-top: 1px solid #4a5568;
  color: #cbd5e0;
  font-size: 14px;
}

.social-links {
  display: flex;
  gap: 16px;
  margin-top: 16px;
}

.social-link {
  width: 36px;
  height: 36px;
  background: #4a5568;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  text-decoration: none;
  transition: all 0.3s;
}

.social-link:hover {
  background: #48bb78;
  transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 968px) {
  .nav-links {
    display: none;
  }

  .hero {
    grid-template-columns: 1fr;
    padding: 60px 5% 40px;
  }

  .hero-content h1 {
    font-size: 36px;
  }

  .hero-image {
    height: 400px;
  }

  .features-grid {
    grid-template-columns: 1fr;
  }

  .content-section {
    grid-template-columns: 1fr;
    gap: 40px;
  }

  .content-section:nth-child(even) .content-image {
    order: 1;
  }

  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
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

  .hero {
    padding: 40px 4%;
    margin-top: 70px;
  }

  .hero-content h1 {
    font-size: 28px;
  }

  .hero-content p {
    font-size: 16px;
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
