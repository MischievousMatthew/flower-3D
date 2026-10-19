<template>
  <Teleport to="body">
    <transition name="srm">
      <div v-if="state.open" class="srm-backdrop">
        <div class="srm" role="alertdialog" aria-modal="true" aria-labelledby="srm-title" aria-describedby="srm-message">
          <span class="srm-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="13" height="10" rx="1.5" /><path d="M7 18h5M9.5 14v4" /><rect x="15" y="9" width="6" height="11" rx="1.2" /><path d="M17.5 17.5h1" /></svg>
          </span>
          <p class="srm-label">Signed out</p>
          <h2 id="srm-title">Your account was logged in on another device</h2>
          <p id="srm-message" class="srm-message">Someone just logged in to this account on a different device or browser, so you've been signed out here.</p>
          <p class="srm-note">If this wasn't you, log in and change your password right away.</p>
          <button ref="logoutButton" type="button" class="srm-btn" @click="logOut">Log out</button>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from "vue";
import { useAuth } from "../../composables/useAuth";
import { clearSessionReplaced, sessionReplaced as state } from "../../utils/sessionReplaced";

/** Shown on the browser whose session another sign-in replaced (mounted once in App.vue) */
const { clearAuthData } = useAuth();
const logoutButton = ref(null);

function logOut() {
  clearAuthData();
  clearSessionReplaced();
  window.location.replace("/guest/login");
}

watch(
  () => state.open,
  (open) => {
    if (open) nextTick(() => logoutButton.value?.focus());
  },
  { immediate: true },
);
</script>

<style scoped>
.srm-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2100;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(30, 27, 24, 0.5);
  backdrop-filter: blur(4px);
}

.srm {
  width: min(420px, 100%);
  padding: 30px 28px 26px;
  border-radius: 16px;
  background: #faf8f4;
  box-shadow: 0 30px 70px -20px rgba(30, 27, 24, 0.45);
  font-family: "Poppins", -apple-system, "Segoe UI", sans-serif;
  text-align: center;
  color: #1e1b18;
}

.srm-icon {
  display: inline-grid;
  place-items: center;
  width: 52px;
  height: 52px;
  margin-bottom: 14px;
  border-radius: 50%;
  background: rgba(184, 101, 88, 0.12);
  color: #8e443a;
}

.srm-icon svg {
  width: 24px;
  height: 24px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.srm-label {
  margin: 0 0 4px;
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #8e443a;
}

.srm h2 {
  margin: 0 0 10px;
  font-family: "Cormorant Garamond", Georgia, serif;
  font-size: 26px;
  font-weight: 500;
  line-height: 1.15;
}

.srm-message {
  margin: 0 0 8px;
  font-size: 14px;
  line-height: 1.6;
}

.srm-note {
  margin: 0 0 22px;
  padding: 10px 12px;
  border-radius: 10px;
  background: rgba(184, 101, 88, 0.08);
  font-size: 12.5px;
  line-height: 1.55;
  color: #8e443a;
}

.srm-btn {
  width: 100%;
  height: 44px;
  border: 0;
  border-radius: 10px;
  background: #1e1b18;
  color: #faf8f4;
  font: 500 14px "Poppins", -apple-system, "Segoe UI", sans-serif;
  cursor: pointer;
  transition: background 0.2s ease;
}

.srm-btn:hover {
  background: #8e443a;
}

.srm-btn:focus-visible {
  outline: 2px solid #b86558;
  outline-offset: 2px;
}

.srm-enter-active,
.srm-leave-active {
  transition: opacity 0.2s ease;
}

.srm-enter-from,
.srm-leave-to {
  opacity: 0;
}
</style>
