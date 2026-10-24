<template>
  <Teleport to="body">
    <transition name="cpm">
      <div v-if="notice.open" class="cpm-backdrop" @click.self="close">
        <div
          class="cpm"
          role="alertdialog"
          aria-modal="true"
          aria-labelledby="cpm-title"
          aria-describedby="cpm-message"
          @keydown.esc="close"
        >
          <span class="cpm-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24">
              <path d="M14.7 6.3a4 4 0 0 0-5.4 5.2L4 16.8a1.4 1.4 0 0 0 0 2l1.2 1.2a1.4 1.4 0 0 0 2 0l5.3-5.3a4 4 0 0 0 5.2-5.4l-2.4 2.4-2.1-.5-.5-2.1z" />
            </svg>
          </span>
          <p class="cpm-label">Checkout unavailable</p>
          <h2 id="cpm-title">We're doing some maintenance</h2>
          <p id="cpm-message" class="cpm-message">{{ notice.message }}</p>
          <p class="cpm-note">Your cart and bouquet are saved. You can keep browsing and check out once we're back.</p>
          <button ref="okButton" type="button" class="cpm-btn" @click="close">Okay, got it</button>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from "vue";
import { checkoutPausedNotice as notice } from "../../composables/usePlatformStatus";

const okButton = ref(null);

function close() {
  notice.open = false;
}

watch(
  () => notice.open,
  (open) => {
    if (open) nextTick(() => okButton.value?.focus());
  },
);
</script>

<style scoped>
.cpm-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(54, 63, 78, 0.45);
  backdrop-filter: blur(4px);
}

.cpm {
  width: min(420px, 100%);
  padding: 30px 28px 26px;
  border-radius: 16px;
  background: #fafbfa;
  box-shadow: 0 30px 70px -20px rgba(54, 63, 78, 0.45);
  font-family: "Poppins", -apple-system, "Segoe UI", sans-serif;
  text-align: center;
  color: #363f4e;
}

.cpm-icon {
  display: inline-grid;
  place-items: center;
  width: 52px;
  height: 52px;
  margin-bottom: 14px;
  border-radius: 50%;
  background: rgba(77, 177, 115, 0.12);
  color: #2e7a4f;
}

.cpm-icon svg {
  width: 24px;
  height: 24px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.cpm-label {
  margin: 0 0 4px;
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #2e7a4f;
}

.cpm h2 {
  margin: 0 0 10px;
  font-family: "Cormorant Garamond", Georgia, serif;
  font-size: 27px;
  font-weight: 500;
  line-height: 1.15;
}

.cpm-message {
  margin: 0 0 8px;
  font-size: 14.5px;
  line-height: 1.6;
}

.cpm-note {
  margin: 0 0 22px;
  font-size: 12.5px;
  line-height: 1.6;
  color: #6a7282;
}

.cpm-btn {
  width: 100%;
  height: 44px;
  border: 0;
  border-radius: 10px;
  background: #363f4e;
  color: #fafbfa;
  font: 500 14px "Poppins", -apple-system, "Segoe UI", sans-serif;
  letter-spacing: 0.02em;
  cursor: pointer;
  transition: background 0.2s ease;
}

.cpm-btn:hover {
  background: #2e7a4f;
}

.cpm-btn:focus-visible {
  outline: 2px solid #4db173;
  outline-offset: 2px;
}

.cpm-enter-active,
.cpm-leave-active {
  transition: opacity 0.2s ease;
}

.cpm-enter-from,
.cpm-leave-to {
  opacity: 0;
}
</style>
