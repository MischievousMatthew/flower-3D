<template>
  <Teleport to="body">
    <transition name="sem">
      <div v-if="state.open" class="sem-backdrop">
        <div class="sem" role="alertdialog" aria-modal="true" aria-labelledby="sem-title" aria-describedby="sem-message">
          <span class="sem-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" /></svg>
          </span>
          <p class="sem-label">Signed out</p>
          <h2 id="sem-title">You were away for a while</h2>
          <p id="sem-message" class="sem-message">To keep your account safe, we sign you out after 15 minutes without any activity.</p>
          <button ref="loginButton" type="button" class="sem-btn" @click="go('/guest/login')">Log in again</button>
          <button type="button" class="sem-link" @click="go('/')">Back to homepage</button>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from "vue";
import { clearSessionExpired, sessionExpired as state } from "../../utils/sessionExpired";

/** Shown when the inactivity timeout signs someone out while the page is open (mounted once in App.vue) */
const loginButton = ref(null);

// Already signed out by now; a full load leaves nothing from the old session on screen
function go(path) {
  clearSessionExpired();
  window.location.replace(path);
}

watch(
  () => state.open,
  (open) => {
    if (open) nextTick(() => loginButton.value?.focus());
  },
  { immediate: true },
);
</script>

<style scoped>
.sem-backdrop {
  position: fixed;
  inset: 0;
  z-index: 2100;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(30, 27, 24, 0.5);
  backdrop-filter: blur(4px);
}

.sem {
  width: min(420px, 100%);
  padding: 30px 28px 22px;
  border-radius: 16px;
  background: #faf8f4;
  box-shadow: 0 30px 70px -20px rgba(30, 27, 24, 0.45);
  font-family: "Poppins", -apple-system, "Segoe UI", sans-serif;
  text-align: center;
  color: #1e1b18;
}

.sem-icon {
  display: inline-grid;
  place-items: center;
  width: 52px;
  height: 52px;
  margin-bottom: 14px;
  border-radius: 50%;
  background: rgba(184, 101, 88, 0.12);
  color: #8e443a;
}

.sem-icon svg {
  width: 24px;
  height: 24px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.6;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.sem-label {
  margin: 0 0 4px;
  font-size: 10.5px;
  font-weight: 500;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #8e443a;
}

.sem h2 {
  margin: 0 0 10px;
  font-family: "Cormorant Garamond", Georgia, serif;
  font-size: 26px;
  font-weight: 500;
  line-height: 1.15;
}

.sem-message {
  margin: 0 0 22px;
  font-size: 14px;
  line-height: 1.6;
}

.sem-btn {
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

.sem-btn:hover {
  background: #8e443a;
}

.sem-link {
  margin-top: 12px;
  padding: 6px 10px;
  border: 0;
  background: none;
  font: 500 13px "Poppins", -apple-system, "Segoe UI", sans-serif;
  color: #6b6159;
  cursor: pointer;
}

.sem-link:hover {
  color: #8e443a;
  text-decoration: underline;
}

.sem-btn:focus-visible,
.sem-link:focus-visible {
  outline: 2px solid #b86558;
  outline-offset: 2px;
}

.sem-enter-active,
.sem-leave-active {
  transition: opacity 0.2s ease;
}

.sem-enter-from,
.sem-leave-to {
  opacity: 0;
}
</style>
