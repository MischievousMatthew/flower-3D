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
    <section class="hero">
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
        <!-- Replace with actual hero image: 1200x800px -->
        <!-- Image should show: 3D flower customization interface or beautiful flower arrangements -->
        <img src="../../../public/1st.jpg" alt="Bloomcraft Image" />
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
import { ref, computed } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();

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
.hero {
  margin-top: 80px;
  padding: 80px 5% 60px;
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 60px;
  align-items: center;
  min-height: calc(100vh - 80px);
}

.hero-content h1 {
  font-size: 48px;
  font-weight: 400;
  line-height: 1.2;
  margin-bottom: 20px;
}

.hero-content h1 .highlight {
  color: #48bb78;
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
  height: 500px;
  background: #f7fafc;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

/* .hero-image::before {
  content: "📸 Hero Image";
  font-size: 18px;
  color: #a0aec0;
  position: absolute;
} */

.hero-image::after {
  /* content: "1200 x 800px"; */
  font-size: 14px;
  color: #cbd5e0;
  position: absolute;
  bottom: 20px;
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
