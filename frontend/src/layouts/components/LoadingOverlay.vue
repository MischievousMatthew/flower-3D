<template>
  <div v-if="visible" class="loading-overlay">
    <div class="loader-content">
      <span class="loader-spinner"></span>
      <p>{{ message }}</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  visible: { type: Boolean, default: false },
  message: { type: String, default: "Loading..." },
});
</script>

<style scoped>
.loading-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(255, 255, 255, 0.5);
  backdrop-filter: blur(3px);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  cursor: wait;
}

.loader-content {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  padding: 40px;
}

.loader-content p {
  color: #2d3748;
  font-size: 16px;
  font-weight: 500;
}

.loader-spinner {
  width: 60px;
  height: 60px;
  border: 3px solid #e2e8f0;
  border-top: 3px solid #48bb78;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}
</style>
