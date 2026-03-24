<template>
  <div class="not-found-page">
    <div class="container">
      <!-- Logo Section -->
      <div class="logo-section">
        <div class="logo">
          <img
            src="../../../public/bloomcraft-blankBg.png"
            alt="BloomCraft Logo"
            width="60"
            height="60"
          />
          <div class="logo-text">
            <h1>BloomCraft</h1>
            <p>Flower Marketplace</p>
          </div>
        </div>
      </div>

      <!-- 404 Content -->
      <div class="content">
        <div class="error-code">404</div>
        <h2>Oops! Page Not Found</h2>
        <p class="message">
          The page you're looking for has wilted away or doesn't exist. Let's
          get you back to the garden!
        </p>

        <!-- Action Buttons -->
        <div class="action-buttons">
          <button class="btn btn-primary" @click="goBack">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="20"
              height="20"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <line x1="19" y1="12" x2="5" y2="12"></line>
              <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Go back to previous page
          </button>
        </div>

        <!-- Flower Animation -->
        <div class="flower-animation">
          <div class="petal petal-1"></div>
          <div class="petal petal-2"></div>
          <div class="petal petal-3"></div>
          <div class="petal petal-4"></div>
          <div class="petal petal-5"></div>
          <div class="petal petal-6"></div>
          <div class="stem"></div>
        </div>
      </div>

      <!-- Footer -->
      <div class="footer">
        <p>Copyright © {{ currentYear }} BloomCraft. All rights reserved.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from "vue";
import { useRouter } from "vue-router";

const router = useRouter();

const currentYear = computed(() => new Date().getFullYear());

const goBack = () => {
  if (window.history.length > 1) {
    router.back();
  } else {
    router.push("/");
  }
};

onMounted(() => {
  document.title = "Page Not Found | BloomCraft";
});
</script>

<style scoped>
.not-found-page {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 50%, #e2e8f0 100%);
  font-family: "Poppins", sans-serif;
  position: relative;
  overflow: hidden;
}

.container {
  text-align: center;
  max-width: 700px;
  padding: 2rem;
  position: relative;
  z-index: 2;
}

.logo-section {
  margin-bottom: 3rem;
}

.logo {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 12px;
  margin-bottom: 1rem;
}

.logo-text h1 {
  font-size: 2rem;
  font-weight: 600;
  color: #2d3748;
  margin: 0;
  line-height: 1.2;
}

.logo-text p {
  color: #718096;
  margin: 0;
  font-size: 1rem;
  font-weight: 400;
}

.error-code {
  font-size: 7rem;
  font-weight: 800;
  background: linear-gradient(135deg, #48bb78, #38a169);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin: 0 0 1.5rem 0;
  line-height: 1;
  animation: bounce 2s infinite;
}

.content h2 {
  font-size: 2.25rem;
  color: #2d3748;
  margin: 0 0 1rem 0;
  font-weight: 400;
}

.message {
  font-size: 1.125rem;
  color: #718096;
  margin: 0 0 3rem 0;
  line-height: 1.7;
  max-width: 450px;
  margin-left: auto;
  margin-right: auto;
}

.action-buttons {
  display: flex;
  gap: 1rem;
  justify-content: center;
  flex-wrap: wrap;
  margin-bottom: 2rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 12px 28px;
  border-radius: 8px;
  font-weight: 500;
  text-decoration: none;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  border: none;
  cursor: pointer;
}

.btn-primary {
  background: #48bb78;
  color: white;
  box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
}

.btn-primary:hover {
  background: #38a169;
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(72, 187, 120, 0.4);
}

.btn-secondary {
  background: #f7fafc;
  color: #2d3748;
  border: 1px solid #e2e8f0;
}

.btn-secondary:hover {
  background: #edf2f7;
  border-color: #cbd5e0;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.footer {
  margin-top: 3rem;
  color: #718096;
  font-size: 0.875rem;
}

/* Flower Animation */
.flower-animation {
  position: relative;
  width: 120px;
  height: 120px;
  margin: 2rem auto;
}

.petal {
  position: absolute;
  width: 40px;
  height: 40px;
  background: #48bb78;
  border-radius: 50% 50% 50% 0;
  top: 20px;
  left: 40px;
  transform-origin: 50% 100%;
  animation: petalFloat 4s infinite ease-in-out;
}

.petal-1 {
  transform: rotate(0deg) scale(1);
  animation-delay: 0s;
}
.petal-2 {
  transform: rotate(60deg) scale(1.1);
  animation-delay: 0.2s;
}
.petal-3 {
  transform: rotate(120deg) scale(0.9);
  animation-delay: 0.4s;
}
.petal-4 {
  transform: rotate(180deg) scale(1.2);
  animation-delay: 0.6s;
}
.petal-5 {
  transform: rotate(240deg) scale(1);
  animation-delay: 0.8s;
}
.petal-6 {
  transform: rotate(300deg) scale(0.95);
  animation-delay: 1s;
}

.stem {
  position: absolute;
  width: 8px;
  height: 60px;
  background: linear-gradient(to top, #2f855a, #38a169);
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  border-radius: 4px 4px 0 0;
  animation: stemSway 3s infinite ease-in-out infinite;
}

/* Animations */
@keyframes bounce {
  0%,
  20%,
  50%,
  80%,
  100% {
    transform: translateY(0);
  }
  40% {
    transform: translateY(-10px);
  }
  60% {
    transform: translateY(-5px);
  }
}

@keyframes petalFloat {
  0%,
  100% {
    transform: rotate(var(--rotation, 0deg)) scale(1) translateY(0);
    opacity: 1;
  }
  50% {
    transform: rotate(var(--rotation, 0deg)) scale(1.1) translateY(-8px);
    opacity: 0.9;
  }
}

@keyframes stemSway {
  0%,
  100% {
    transform: translateX(-50%) rotate(-1deg);
  }
  50% {
    transform: translateX(-50%) rotate(1deg);
  }
}

/* Responsive */
@media (max-width: 768px) {
  .container {
    padding: 1.5rem 1rem;
  }

  .error-code {
    font-size: 5rem;
  }

  .content h2 {
    font-size: 1.875rem;
  }

  .logo-text h1 {
    font-size: 1.5rem;
  }

  .action-buttons {
    flex-direction: column;
    align-items: center;
  }

  .btn {
    width: 100%;
    max-width: 300px;
    justify-content: center;
  }

  .flower-animation {
    width: 100px;
    height: 100px;
  }

  .petal {
    width: 32px;
    height: 32px;
  }
}

@media (max-width: 480px) {
  .error-code {
    font-size: 4rem;
  }

  .content h2 {
    font-size: 1.5rem;
  }
}
</style>
