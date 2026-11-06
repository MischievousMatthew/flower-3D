<template>
  <!-- A wrong key looks exactly like any other missing page -->
  <NotFound v-if="state === 'missing'" />

  <div v-else class="al-page">
    <main class="al-main">
      <section class="al-card" aria-labelledby="al-title">
        <header class="al-brand">
          <img :src="isDark ? '/bloomcraft-darkmode-removebg.png' : '/bloomcraft-blankBg.png'" alt="" width="36" height="36" />
          <span class="al-name">BloomCraft</span>
          <span class="al-badge">Admin</span>
        </header>

        <!-- Checking the key: the form's shape, no spinner -->
        <div v-if="state === 'checking'" class="al-skeleton" aria-hidden="true">
          <span class="bc-skeleton" style="width: 70%; height: 28px"></span>
          <span class="bc-skeleton" style="width: 90%; height: 14px"></span>
          <span class="bc-skeleton al-sk-field"></span>
          <span class="bc-skeleton al-sk-field"></span>
          <span class="bc-skeleton al-sk-btn"></span>
        </div>
        <p v-if="state === 'checking'" class="bc-sr-only" role="status">Loading the sign-in form</p>

        <!-- The server didn't answer -->
        <div v-else-if="state === 'unreachable'" class="al-unreachable" role="alert">
          <h1 id="al-title">Can't reach BloomCraft</h1>
          <p class="al-lead">The server may be waking up. This usually takes under a minute.</p>
          <button type="button" class="al-btn" @click="checkGate">Try again</button>
        </div>

        <template v-else>
          <h1 id="al-title">Sign in to the admin console</h1>
          <p class="al-lead">For BloomCraft administrators only.</p>

          <!-- Signed in elsewhere: ask before taking over -->
          <div v-if="sessionActive" class="al-note warn" role="alertdialog" aria-labelledby="al-elsewhere">
            <strong id="al-elsewhere">This account is signed in somewhere else</strong>
            <p>Continuing here signs it out on the other device or browser.</p>
            <div class="al-note-actions">
              <button ref="continueButton" type="button" class="al-btn small" :disabled="loading" @click="submit(true)">
                {{ loading ? "Signing in…" : "Sign in here instead" }}
              </button>
              <button type="button" class="al-ghost" :disabled="loading" @click="sessionActive = false">Cancel</button>
            </div>
          </div>

          <form class="al-form" novalidate @submit.prevent="submit(false)">
            <div class="al-field">
              <label for="al-user">Username or email</label>
              <input
                id="al-user"
                ref="userInput"
                v-model.trim="form.username"
                type="text"
                autocomplete="username"
                autocapitalize="none"
                spellcheck="false"
                :aria-invalid="problem?.field === 'username' || undefined"
                :disabled="loading"
                required
              />
            </div>

            <div class="al-field">
              <label for="al-pass">Password</label>
              <div class="al-pass">
                <input
                  id="al-pass"
                  ref="passInput"
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  autocomplete="current-password"
                  :aria-invalid="problem?.field === 'password' || undefined"
                  aria-describedby="al-problem"
                  :disabled="loading"
                  required
                />
                <button
                  type="button"
                  class="al-eye"
                  :aria-label="showPassword ? 'Hide password' : 'Show password'"
                  :aria-pressed="showPassword"
                  @click="showPassword = !showPassword"
                >
                  <svg v-if="showPassword" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8M9.9 5.2A9.7 9.7 0 0 1 12 5c5 0 9 4.5 10 7-.4 1-1.2 2.3-2.4 3.5M6.2 6.2C4.2 7.6 2.7 9.6 2 12c1 2.5 5 7 10 7 1.7 0 3.3-.5 4.6-1.3" /></svg>
                  <svg v-else viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12c1-2.5 5-7 10-7s9 4.5 10 7c-1 2.5-5 7-10 7S3 14.5 2 12Z" /><circle cx="12" cy="12" r="3" /></svg>
                </button>
              </div>
            </div>

            <!-- Fixed space, so a message never pushes the button around -->
            <p id="al-problem" class="al-problem" :class="problem?.tone" aria-live="polite">
              <template v-if="problem">
                <strong>{{ problem.title }}.</strong> {{ problem.body }}
                <template v-if="waitLeft > 0"> Try again in {{ formatWait(waitLeft) }}.</template>
              </template>
            </p>

            <button type="submit" class="al-btn" :disabled="loading || waitLeft > 0">
              {{ serverWaking ? "Waking the server…" : loading ? "Signing in…" : "Sign in" }}
            </button>
          </form>

          <p class="al-foot">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 4 6v6c0 4.5 3.4 8.3 8 9 4.6-.7 8-4.5 8-9V6l-8-3Z" /></svg>
            Protected area. Every sign-in is recorded.
          </p>
        </template>
      </section>
    </main>
  </div>
</template>

<script setup>
// The hidden admin sign-in page, /console/<key>. Nothing on the site links
// here. The server checks the key; a wrong one shows the normal 404 page.
import { nextTick, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import api from "../../plugins/axios";
import NotFound from "../guest/NotFound.vue";
import { useAuth } from "../../composables/useAuth";
import { useAdminTheme } from "../../composables/useAdminTheme";
import { explainLoginProblem, formatWait } from "../../utils/loginProblems";
import { getPreferredAuthToken } from "../../utils/authSession";

const route = useRoute();
const router = useRouter();
const { adminLogin, loading, serverWaking } = useAuth();
const { isDark } = useAdminTheme();

const state = ref("checking"); // checking | ready | missing | unreachable
const form = reactive({ username: "", password: "" });
const showPassword = ref(false);
const problem = ref(null);
const sessionActive = ref(false);
const waitLeft = ref(0);
const userInput = ref(null);
const passInput = ref(null);
const continueButton = ref(null);
let waitTimer = null;

const key = () => String(route.params.key || "");

async function checkGate() {
  state.value = "checking";
  try {
    await api.get(`/admin-gate/${encodeURIComponent(key())}`, { quietStatuses: [404] });
  } catch (err) {
    state.value = err?.response?.status === 404 ? "missing" : "unreachable";
    return;
  }
  // Already signed in as an admin: straight to the panel
  const stored = (() => {
    try {
      return JSON.parse(localStorage.getItem("user") || "null");
    } catch {
      return null;
    }
  })();
  if (stored?.role === "admin" && getPreferredAuthToken("/admin")) {
    router.replace("/admin/dashboard");
    return;
  }
  state.value = "ready";
  await nextTick();
  userInput.value?.focus();
}

function startWait(seconds) {
  clearInterval(waitTimer);
  waitLeft.value = Math.ceil(seconds);
  waitTimer = setInterval(() => {
    waitLeft.value = Math.max(0, waitLeft.value - 1);
    if (!waitLeft.value) clearInterval(waitTimer);
  }, 1000);
}

async function submit(continueHere) {
  if (loading.value) return;
  if (!form.username || !form.password) {
    problem.value = { tone: "info", title: "Almost there", body: "Enter your username and password.", field: !form.username ? "username" : "password" };
    (!form.username ? userInput : passInput).value?.focus();
    return;
  }
  problem.value = null;
  const result = await adminLogin(key(), { ...form, continueHere });
  if (result.success) return;

  if (result.status === 404) {
    // The key was changed while this page was open
    state.value = "missing";
    return;
  }
  if (result.code === "session_active") {
    sessionActive.value = true;
    await nextTick();
    continueButton.value?.focus();
    return;
  }
  sessionActive.value = false;
  // No "forgot password" link here: admins reset theirs from the panel
  problem.value = explainLoginProblem(result);
  if (problem.value.countdown) startWait(problem.value.countdown);
  if (problem.value.field === "password") {
    form.password = "";
    passInput.value?.focus();
  }
}

// Keep search engines away, and give the tab a plain title
let robots = null;
onMounted(() => {
  document.title = "Admin sign-in · BloomCraft";
  robots = document.createElement("meta");
  robots.name = "robots";
  robots.content = "noindex, nofollow";
  document.head.appendChild(robots);
  checkGate();
});
onBeforeUnmount(() => {
  clearInterval(waitTimer);
  robots?.remove();
});
</script>

<style scoped>
.al-page *,
.al-page *::before,
.al-page *::after {
  box-sizing: border-box;
}
.al-page {
  min-height: 100vh;
  min-height: 100dvh;
  display: flex;
  background:
    radial-gradient(circle at 1px 1px, var(--a-border) 1px, transparent 0) 0 0 / 22px 22px,
    var(--a-bg);
  color: var(--a-text);
  font-family: "Poppins", system-ui, sans-serif;
}
.al-main {
  flex: 1;
  display: grid;
  place-items: center;
  padding: 32px 16px;
}
.al-card {
  width: 100%;
  max-width: 400px;
  padding: 32px 28px 24px;
  border: 1px solid var(--a-border);
  border-top: 3px solid var(--a-accent);
  border-radius: 16px;
  background: var(--a-surface);
  box-shadow: 0 18px 48px rgba(15, 23, 42, 0.08), 0 2px 6px rgba(15, 23, 42, 0.04);
}
.al-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 28px;
}
.al-brand img {
  width: 36px;
  height: 36px;
  object-fit: contain;
}
.al-name {
  font-size: 17px;
  font-weight: 600;
  letter-spacing: -0.01em;
}
.al-badge {
  margin-left: auto;
  padding: 3px 10px;
  border-radius: 999px;
  background: var(--a-accent-soft-2);
  color: var(--a-accent-text);
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
h1 {
  margin: 0;
  font-size: 22px;
  font-weight: 600;
  line-height: 1.3;
  letter-spacing: -0.01em;
}
.al-lead {
  margin: 6px 0 22px;
  color: var(--a-muted);
  font-size: 14px;
  line-height: 1.5;
}
.al-form {
  display: grid;
  gap: 16px;
}
.al-field {
  display: grid;
  gap: 6px;
}
.al-field label {
  color: var(--a-text-2);
  font-size: 13.5px;
  font-weight: 500;
}
.al-field input {
  width: 100%;
  min-height: 46px;
  padding: 0 14px;
  border: 1px solid var(--a-border-strong);
  border-radius: 10px;
  background: var(--a-surface);
  color: var(--a-text);
  font: inherit;
  font-size: 16px; /* no zoom on phones */
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.al-field input:focus {
  outline: none;
  border-color: var(--a-accent);
  box-shadow: 0 0 0 3px var(--a-accent-soft-2);
}
.al-field input[aria-invalid="true"] {
  border-color: var(--a-red-text);
}
.al-field input:disabled {
  opacity: 0.7;
}
.al-pass {
  position: relative;
}
.al-pass input {
  padding-right: 52px;
}
.al-eye {
  position: absolute;
  top: 50%;
  right: 4px;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--a-muted);
  cursor: pointer;
  transform: translateY(-50%);
}
.al-eye:hover {
  color: var(--a-text);
}
.al-eye svg,
.al-foot svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.al-problem {
  min-height: 42px;
  margin: -4px 0 0;
  color: var(--a-text-2);
  font-size: 13.5px;
  line-height: 1.5;
}
.al-problem.warn,
.al-problem.lock {
  color: var(--a-amber-text);
}
.al-problem.offline {
  color: var(--a-red-text);
}
.al-btn {
  min-height: 48px;
  padding: 0 20px;
  border: 0;
  border-radius: 10px;
  background: var(--a-accent);
  color: var(--a-accent-ink);
  font: inherit;
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.15s ease;
}
.al-btn:hover:not(:disabled) {
  background: var(--a-accent-hover);
}
.al-btn:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}
.al-btn.small {
  min-height: 44px;
  font-size: 14px;
}
.al-ghost {
  min-height: 44px;
  padding: 0 14px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: var(--a-text-2);
  font: inherit;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
}
.al-ghost:hover {
  background: var(--a-subtle-2);
}
.al-btn:focus-visible,
.al-ghost:focus-visible,
.al-eye:focus-visible {
  outline: 2px solid var(--a-accent);
  outline-offset: 2px;
}
.al-note {
  margin: 0 0 18px;
  padding: 14px 16px;
  border: 1px solid var(--a-amber-border);
  border-radius: 12px;
  background: var(--a-amber-soft);
  font-size: 14px;
}
.al-note strong {
  display: block;
  color: var(--a-amber-text);
}
.al-note p {
  margin: 4px 0 12px;
  color: var(--a-text-2);
  line-height: 1.5;
}
.al-note-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.al-foot {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 22px 0 0;
  padding-top: 16px;
  border-top: 1px solid var(--a-border);
  color: var(--a-muted);
  font-size: 12.5px;
}
.al-foot svg {
  flex: none;
  width: 16px;
  height: 16px;
}
.al-unreachable .al-btn {
  width: 100%;
}
.al-skeleton {
  display: grid;
  gap: 12px;
}
.al-skeleton .bc-skeleton {
  display: block;
  background: linear-gradient(100deg, var(--a-subtle-2) 30%, var(--a-track) 50%, var(--a-subtle-2) 70%);
  background-size: 300% 100%;
}
.al-sk-field {
  height: 46px;
  margin-top: 14px;
}
.al-sk-btn {
  height: 48px;
  margin-top: 28px;
}
@media (max-width: 480px) {
  .al-card {
    padding: 26px 20px 20px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .al-field input,
  .al-btn {
    transition: none;
  }
}
</style>
