<template>
  <main class="fcp">
    <section class="fcp-card" aria-labelledby="fcp-title">
      <header class="fcp-brand">
        <img src="/bloomcraft-blankBg.png" alt="" width="36" height="36" />
        <span>BloomCraft <em>Vendor</em></span>
      </header>

      <!-- Done: a clear success state while the portal opens -->
      <div v-if="done" class="fcp-done" role="status">
        <span class="fcp-badge ok" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
        </span>
        <h1 id="fcp-title">Password updated</h1>
        <p>Your new password is set. Taking you to your vendor portal…</p>
      </div>

      <template v-else>
        <span class="fcp-badge" aria-hidden="true">
          <svg viewBox="0 0 24 24">
            <path d="M12 3 5 6v5c0 4.4 3 8.3 7 9.5 4-1.2 7-5.1 7-9.5V6z" />
            <rect x="9" y="11" width="6" height="5" rx="1.2" />
            <path d="M10.2 11V9.6a1.8 1.8 0 0 1 3.6 0V11" />
          </svg>
        </span>
        <h1 id="fcp-title">Set your own password</h1>
        <p class="fcp-lede">Your account was created with a temporary password. Choose a new one that only you know to open your vendor portal.</p>

        <div v-if="errorMessage" class="fcp-alert" role="alert">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7.5v5.5M12 16.5v.01" /></svg>
          <span>{{ errorMessage }}</span>
        </div>

        <form class="fcp-form" novalidate @submit.prevent="changePassword">
          <!-- Temporary password -->
          <div class="fcp-field">
            <label for="fcp-current">Temporary password</label>
            <div class="fcp-input" :class="{ bad: fieldError('current_password') }">
              <svg class="fcp-lead" viewBox="0 0 24 24" aria-hidden="true"><circle cx="8" cy="15" r="4" /><path d="M11 12l8-8M16 7l3 3" /></svg>
              <input
                id="fcp-current"
                v-model="form.current_password"
                :type="shown.current ? 'text' : 'password'"
                autocomplete="current-password"
                placeholder="The password you signed in with"
                :aria-invalid="fieldError('current_password') ? 'true' : 'false'"
                :aria-describedby="fieldError('current_password') ? 'fcp-current-error' : undefined"
                @input="clearError('current_password')"
              />
              <button type="button" class="fcp-eye" :aria-label="shown.current ? 'Hide password' : 'Show password'" :aria-pressed="shown.current" @click="shown.current = !shown.current">
                <EyeIcon :open="shown.current" />
              </button>
            </div>
            <p v-if="fieldError('current_password')" id="fcp-current-error" class="fcp-error">{{ fieldError("current_password") }}</p>
          </div>

          <!-- New password -->
          <div class="fcp-field">
            <label for="fcp-new">New password</label>
            <div class="fcp-input" :class="{ bad: fieldError('password') }">
              <svg class="fcp-lead" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
              <input
                id="fcp-new"
                v-model="form.password"
                :type="shown.password ? 'text' : 'password'"
                autocomplete="new-password"
                placeholder="At least 8 characters"
                :aria-invalid="fieldError('password') ? 'true' : 'false'"
                :aria-describedby="fieldError('password') ? 'fcp-new-error' : undefined"
                @input="clearError('password')"
              />
              <button type="button" class="fcp-eye" :aria-label="shown.password ? 'Hide password' : 'Show password'" :aria-pressed="shown.password" @click="shown.password = !shown.password">
                <EyeIcon :open="shown.password" />
              </button>
            </div>
            <p v-if="fieldError('password')" id="fcp-new-error" class="fcp-error">{{ fieldError("password") }}</p>
            <PasswordRules :password="form.password" tone="vendor" />
            <PasswordSuggest tone="vendor" :current="form.password" @use="useSuggested" />
          </div>

          <!-- Confirm -->
          <div class="fcp-field">
            <label for="fcp-confirm">Type it again</label>
            <div class="fcp-input" :class="{ bad: fieldError('password_confirmation'), good: matches }">
              <svg class="fcp-lead" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
              <input
                id="fcp-confirm"
                v-model="form.password_confirmation"
                :type="shown.confirm ? 'text' : 'password'"
                autocomplete="new-password"
                placeholder="Repeat the new password"
                :aria-invalid="fieldError('password_confirmation') ? 'true' : 'false'"
                :aria-describedby="fieldError('password_confirmation') ? 'fcp-confirm-error' : undefined"
                @input="clearError('password_confirmation')"
              />
              <button type="button" class="fcp-eye" :aria-label="shown.confirm ? 'Hide password' : 'Show password'" :aria-pressed="shown.confirm" @click="shown.confirm = !shown.confirm">
                <EyeIcon :open="shown.confirm" />
              </button>
            </div>
            <p v-if="fieldError('password_confirmation')" id="fcp-confirm-error" class="fcp-error">{{ fieldError("password_confirmation") }}</p>
            <p v-else-if="matches" class="fcp-match">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" /></svg>
              Passwords match
            </p>
          </div>

          <button type="submit" class="fcp-submit" :disabled="loading" :aria-busy="loading">
            {{ loading ? "Updating password…" : "Update password" }}
            <svg v-if="!loading" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
          </button>
        </form>

        <footer class="fcp-foot">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 5 6v5c0 4.4 3 8.3 7 9.5 4-1.2 7-5.1 7-9.5V6z" /></svg>
          <span>You'll only be asked once. <button type="button" class="fcp-link" @click="signOut">Not you? Sign out</button></span>
        </footer>
      </template>
    </section>
  </main>
</template>

<script setup>
import { computed, h, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import api from "../../plugins/axios";
import { useAuth } from "../../composables/useAuth";
import { passwordOk } from "../../utils/passwordRules";
import PasswordRules from "../../components/auth/PasswordRules.vue";
import PasswordSuggest from "../../components/auth/PasswordSuggest.vue";

/** Show/hide icon: an open eye, or one with a line through it */
const EyeIcon = (props) =>
  h("svg", { viewBox: "0 0 24 24", "aria-hidden": "true" }, props.open
    ? [h("path", { d: "M3 3l18 18M10.6 5.1A9.7 9.7 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4.1M6.6 6.6C3.8 8.4 2 12 2 12s3.6 7 10 7c1.8 0 3.4-.6 4.7-1.4M9.9 9.9a3 3 0 0 0 4.2 4.2" })]
    : [h("path", { d: "M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" }), h("circle", { cx: 12, cy: 12, r: 3 })]);
EyeIcon.props = { open: Boolean };

const router = useRouter();
const auth = useAuth();
const loading = ref(false);
const done = ref(false);

const form = reactive({ current_password: "", password: "", password_confirmation: "" });
const shown = reactive({ current: false, password: false, confirm: false });
const errors = ref({});
const errorMessage = ref("");

const matches = computed(() => !!form.password_confirmation && form.password_confirmation === form.password);
const fieldError = (key) => {
  const value = errors.value[key];
  return Array.isArray(value) ? value[0] : value || "";
};
const clearError = (key) => {
  if (errors.value[key]) errors.value = { ...errors.value, [key]: "" };
};

function useSuggested(pw) {
  form.password = pw;
  form.password_confirmation = pw;
  clearError("password");
  clearError("password_confirmation");
}

async function signOut() {
  await auth.logout();
}

const changePassword = async () => {
  errors.value = {};
  errorMessage.value = "";

  if (!form.current_password) errors.value.current_password = "Enter the temporary password you signed in with.";
  if (!passwordOk(form.password)) errors.value.password = "Use at least 8 characters with an uppercase letter, a lowercase letter and a number.";
  else if (form.password !== form.password_confirmation) errors.value.password_confirmation = "The two passwords don't match.";
  if (Object.keys(errors.value).length) return;

  loading.value = true;
  try {
    const { data } = await api.put("/vendor/profile/change-password", { ...form });
    if (data.success) {
      done.value = true;
      // Clears the "change your password" flag before opening the portal
      await auth.loadUser();
      setTimeout(() => router.push("/vendor/products"), 1500);
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
      if (!Object.keys(errors.value).length) errorMessage.value = error.response.data.message || "Check the passwords and try again.";
    } else {
      errorMessage.value = error.response?.data?.message || "We couldn't update your password. Please try again.";
    }
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.fcp {
  --fcp-accent: var(--bc-accent-solid, #363f4e);
  --fcp-accent-hover: var(--bc-accent-solid-hover, #262d39);
  --fcp-accent-soft: color-mix(in srgb, var(--bc-accent, #4db173) 14%, transparent);
  display: grid;
  place-items: center;
  min-height: 100vh;
  min-height: 100dvh;
  padding: 32px 16px;
  background:
    radial-gradient(60% 50% at 50% 0%, color-mix(in srgb, var(--bc-accent, #4db173) 10%, transparent), transparent 70%),
    var(--v-bg);
  color: var(--v-text);
}

.fcp-card {
  display: grid;
  gap: 18px;
  width: 100%;
  max-width: 460px;
  padding: 32px 32px 26px;
  border: 1px solid var(--v-border);
  border-radius: 20px;
  background: var(--v-surface);
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 24px 48px -24px rgba(15, 23, 42, 0.18);
}

.fcp-brand {
  display: flex;
  align-items: center;
  gap: 10px;
  color: var(--v-text);
  font-size: 15px;
  font-weight: 600;
}

.fcp-brand em {
  color: var(--v-muted);
  font-style: normal;
  font-weight: 500;
}

.fcp-badge {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  margin-top: 6px;
  border-radius: 16px;
  background: var(--fcp-accent-soft);
  color: var(--fcp-accent);
}

.fcp-badge svg {
  width: 26px;
  height: 26px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fcp-badge.ok {
  background: var(--v-green-soft);
  color: var(--v-green-text);
}

.fcp h1 {
  margin: 0;
  color: var(--v-text);
  font-size: 24px;
  font-weight: 650;
  letter-spacing: -0.01em;
}

.fcp-lede,
.fcp-done p {
  margin: -8px 0 0;
  color: var(--v-muted);
  font-size: 14.5px;
  line-height: 1.6;
}

.fcp-done {
  display: grid;
  justify-items: start;
  gap: 16px;
}

.fcp-alert {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 11px 13px;
  border-radius: 12px;
  background: var(--v-red-soft);
  color: var(--v-red-text);
  font-size: 13.5px;
  line-height: 1.5;
}

.fcp-alert svg {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
}

.fcp-form {
  display: grid;
  gap: 18px;
}

.fcp-field {
  display: grid;
  gap: 8px;
}

.fcp-field label {
  color: var(--v-text-2);
  font-size: 13px;
  font-weight: 600;
}

.fcp-input {
  position: relative;
  display: flex;
  align-items: center;
  border: 1px solid var(--v-border-strong);
  border-radius: 12px;
  background: var(--v-input);
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.fcp-input:focus-within {
  border-color: var(--fcp-accent);
  box-shadow: 0 0 0 4px var(--fcp-accent-soft);
}

.fcp-input.bad {
  border-color: var(--v-red-text);
}

.fcp-input.good {
  border-color: var(--v-green-text);
}

.fcp-lead {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  margin-left: 14px;
  fill: none;
  stroke: var(--v-muted);
  stroke-width: 1.7;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fcp-input input {
  flex: 1;
  min-width: 0;
  height: 48px;
  padding: 0 8px 0 10px;
  border: 0;
  background: transparent;
  color: var(--v-text);
  font-size: 15px;
  outline: none;
}

.fcp-input input::placeholder {
  color: var(--v-faint);
}

.fcp-eye {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  margin-right: 2px;
  border: 0;
  border-radius: 10px;
  background: none;
  color: var(--v-muted);
  cursor: pointer;
}

.fcp-eye:hover {
  color: var(--v-text);
}

.fcp-eye :deep(svg),
.fcp-match svg,
.fcp-submit svg,
.fcp-foot svg {
  width: 18px;
  height: 18px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.fcp-error {
  margin: 0;
  color: var(--v-red-text);
  font-size: 12.5px;
}

.fcp-match {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  color: var(--v-green-text);
  font-size: 12.5px;
  font-weight: 600;
}

.fcp-match svg {
  width: 15px;
  height: 15px;
}

.fcp-submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 50px;
  margin-top: 4px;
  border: 0;
  border-radius: 12px;
  background: var(--fcp-accent);
  color: var(--bc-accent-ink, #ffffff);
  font-size: 15px;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s ease;
}

.fcp-submit:hover:not(:disabled) {
  background: var(--fcp-accent-hover);
}

.fcp-submit:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

.fcp-foot {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-top: 16px;
  border-top: 1px solid var(--v-border);
  color: var(--v-muted);
  font-size: 12.5px;
}

.fcp-foot svg {
  flex-shrink: 0;
  width: 16px;
  height: 16px;
}

.fcp-link {
  margin-left: 4px;
  padding: 0;
  border: 0;
  background: none;
  color: var(--v-text);
  font: inherit;
  font-weight: 600;
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}

.fcp-eye:focus-visible,
.fcp-submit:focus-visible,
.fcp-link:focus-visible {
  outline: 2px solid var(--fcp-accent);
  outline-offset: 2px;
}

@media (max-width: 480px) {
  .fcp-card {
    padding: 24px 18px 20px;
    border-radius: 16px;
  }
}
</style>
