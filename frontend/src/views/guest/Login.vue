<template>
  <AuthShell
    :title="twoFactorRequired ? (twoFactorMethod === 'passkey' ? 'Enter your passkey' : 'Check your email') : 'Welcome back'"
    :lead="twoFactorRequired ? twoFactorLead : 'Good to see you again. Sign in to pick up right where you left off.'"
  >
    <template #top>
      New to BloomCraft? <router-link to="/guest/register" class="af-link">Create an account</router-link>
    </template>

    <!-- Username and password -->
    <form v-if="!twoFactorRequired" class="af-form" novalidate @submit.prevent="handleLogin()">
      <div class="af-field">
        <label class="af-label" for="lg-user">Username or email</label>
        <input
          id="lg-user"
          ref="userInput"
          v-model.trim="form.username"
          class="af-input" placeholder=" "
          type="text"
          autocomplete="username"
          autocapitalize="none"
          spellcheck="false"
          :aria-invalid="fieldError.username ? 'true' : 'false'"
          aria-describedby="lg-user-note"
          @input="fieldError.username = ''"
        />
        <p v-if="fieldError.username" id="lg-user-note" class="af-note bad">{{ fieldError.username }}</p>
      </div>

      <div class="af-field">
        <label class="af-label" for="lg-pass">Password</label>
        <PasswordField
          id="lg-pass"
          ref="passInput"
          v-model="form.password"
          :invalid="!!fieldError.password || notice?.field === 'password'"
          describedby="lg-pass-note"
          @update:model-value="fieldError.password = ''"
        />
        <p v-if="fieldError.password" id="lg-pass-note" class="af-note bad">{{ fieldError.password }}</p>
        <router-link :to="{ name: 'ForgotPassword', query: { u: form.username || undefined } }" class="af-link lg-forgot">Forgot password?</router-link>
      </div>

      <button type="submit" class="af-btn" :class="{ busy: loading }" :disabled="loading || locked">
        {{ loading ? (slow ? "Waking up BloomCraft…" : "Signing in…") : locked ? "Sign-in paused" : "Sign in" }}
      </button>
      <p v-if="loading && slow" class="af-note lg-slow" role="status">
        Our server naps when nobody's around, so the first sign-in of the day can take up to a minute. Keep this page open.
      </p>
    </form>

    <!-- Two-step verification -->
    <section v-else class="af-form">
      <CodeBoxes
        ref="codeBoxes"
        v-model="twoFactorCode"
        :masked="twoFactorMethod === 'passkey'"
        :invalid="!!codeError"
        :disabled="loading"
        :label="twoFactorMethod === 'passkey' ? 'Six-digit passkey' : 'Six-digit email code'"
        @update:model-value="codeError = ''"
        @complete="completeTwoFactorLogin"
      />
      <p v-if="codeError" class="af-note bad" role="alert">{{ codeError }}</p>
      <CodeTimer v-else-if="twoFactorMethod === 'otp' && codeUntil" :until="codeUntil" />
      <button type="button" class="af-btn" :class="{ busy: loading }" :disabled="loading || twoFactorCode.length !== 6" @click="completeTwoFactorLogin">
        {{ loading ? "Checking…" : "Continue" }}
      </button>
      <div class="lg-alt">
        <button v-if="twoFactorMethod === 'passkey'" type="button" class="af-plain" :disabled="loading || sendingCode" @click="requestLoginOtp">
          {{ sendingCode ? "Sending a code…" : "Forgot your passkey? Email me a code" }}
        </button>
        <button v-else type="button" class="af-plain" :disabled="loading || sendingCode || resendIn > 0" @click="requestLoginOtp">
          {{ resendIn > 0 ? `Send a new code in ${resendIn}s` : "Send a new code" }}
        </button>
        <button type="button" class="af-plain lg-muted" :disabled="loading" @click="cancelTwoFactorLogin">Use a different account</button>
      </div>
    </section>

    <p v-if="!twoFactorRequired" class="af-foot">
      Selling flowers? <router-link to="/guest/vendor_register" class="af-link">Apply as a vendor</router-link>
    </p>

    <!-- The account is in use on another browser: ask before signing in here -->
    <Teleport to="body">
      <div v-if="sessionActiveOpen" class="bc-theme lg-backdrop" @click.self="sessionActiveOpen = false">
        <div class="lg-modal" role="alertdialog" aria-modal="true" aria-labelledby="lg-session-title" aria-describedby="lg-session-copy" @keydown.esc="sessionActiveOpen = false">
          <h2 id="lg-session-title">You're signed in somewhere else</h2>
          <p id="lg-session-copy">This account is open on another device or browser. If you continue here, it will be signed out there.</p>
          <button ref="continueButton" type="button" class="af-btn" :class="{ busy: loading }" :disabled="loading" @click="continueHere">
            {{ loading ? "Signing in…" : "Sign in here instead" }}
          </button>
          <button type="button" class="af-btn ghost" :disabled="loading" @click="sessionActiveOpen = false">Cancel</button>
          <p class="lg-modal-note">Not you? Someone may know your password. Sign in and <router-link :to="{ name: 'ForgotPassword', query: { u: form.username || undefined } }" class="af-link">change it</router-link> right away.</p>
        </div>
      </div>
    </Teleport>
  </AuthShell>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import AuthShell from "../../components/auth/AuthShell.vue";
import PasswordField from "../../components/auth/PasswordField.vue";
import CodeBoxes from "../../components/auth/CodeBoxes.vue";
import CodeTimer from "../../components/auth/CodeTimer.vue";
import { useAuth } from "../../composables/useAuth";
import { useSiteContent } from "../../composables/useSiteContent";
import { explainLoginProblem, formatWait } from "../../utils/loginProblems";
import { toast } from "@/utils/snackbar";
import api from "../../plugins/axios";
import { hasStoredAuthSession, getPreferredAuthToken, getPreferredUserType } from "../../utils/authSession";

const route = useRoute();
const router = useRouter();
const { combinedLogin, completeTwoFactorLogin: finishTwoFactorLogin, loading } = useAuth();
const { site } = useSiteContent();
const supportEmail = computed(() => site.settings?.contact_email || "");

const form = reactive({ username: typeof route.query.u === "string" ? route.query.u : "", password: "" });
const fieldError = reactive({ username: "", password: "" });
const notice = ref(null);
const locked = ref(false);
const slow = ref(false);
const userInput = ref(null);
const passInput = ref(null);

const twoFactorRequired = ref(false);
const twoFactorMethod = ref("passkey");
const twoFactorChallengeToken = ref("");
const twoFactorCode = ref("");
const maskedEmail = ref("");
const codeError = ref("");
const sendingCode = ref(false);
const resendIn = ref(0);
const codeUntil = ref(0);
const codeBoxes = ref(null);
let resendTimer = null;
const twoFactorLead = computed(() =>
  twoFactorMethod.value === "passkey"
    ? "Your account has two-step sign-in. Enter the six-digit passkey you chose."
    : `We emailed a six-digit code to ${maskedEmail.value || "your email"}.`,
);

const sessionActiveOpen = ref(false);
const continueButton = ref(null);

// Slow first sign-in: the free server sleeps when idle and takes a while to wake
let slowTimer = null;
const withSlowHint = async (work) => {
  slow.value = false;
  clearTimeout(slowTimer);
  slowTimer = setTimeout(() => (slow.value = true), 5000);
  try {
    return await work();
  } finally {
    clearTimeout(slowTimer);
    slow.value = false;
  }
};

const show = (result) => {
  notice.value = explainLoginProblem(result);
  locked.value = notice.value.tone === "lock" && !!notice.value.countdown;
  if (notice.value.field === "password") {
    nextTick(() => passInput.value?.focus());
  }
};
// Every sign-in message is a snackbar, with what to do next as its button
let lockTimer = null;
const SNACK_TYPE = { success: "success", info: "info", warn: "warning", lock: "warning", offline: "error" };
watch(notice, (n) => {
  if (!n) return;
  const wait = n.countdown ? ` Try again in ${formatWait(n.countdown)}.` : "";
  let action = null;
  if (n.actions?.includes("forgot")) action = { label: "Forgot password", to: { name: "ForgotPassword", query: { u: form.username || undefined } } };
  else if (n.actions?.includes("retry")) action = { label: "Try again", onClick: () => (twoFactorRequired.value ? completeTwoFactorLogin() : handleLogin()) };
  else if (n.actions?.includes("support") && supportEmail.value) action = { label: "Contact support", onClick: () => (window.location.href = `mailto:${supportEmail.value}`) };
  const title = n.title.replace(/[.!]$/, "");
  toast[SNACK_TYPE[n.tone] ?? "error"](`${title}. ${n.body ?? ""}${wait}`.trim(), {
    toastId: "login-notice",
    action,
    // A lock or being offline stays until it's over (or closed)
    autoClose: n.tone === "lock" || n.tone === "offline" ? false : undefined,
  });
  clearTimeout(lockTimer);
  if (n.tone === "lock" && n.countdown) lockTimer = setTimeout(lockEnded, n.countdown * 1000);
});
onBeforeUnmount(() => clearTimeout(lockTimer));

const lockEnded = () => {
  locked.value = false;
  notice.value = { tone: "success", title: "You can try again now", body: "Take your time with the password.", actions: ["forgot"] };
};

const handleLogin = async (options = {}) => {
  fieldError.username = form.username ? "" : "Enter your username or email.";
  fieldError.password = form.password ? "" : "Enter your password.";
  if (fieldError.username || fieldError.password) {
    nextTick(() => (fieldError.username ? userInput.value : passInput.value)?.focus());
    return;
  }
  if (locked.value) return;
  notice.value = null;

  try {
    const result = await withSlowHint(() => combinedLogin({ ...form, continueHere: options.continueHere === true }));
    if (result.requiresTwoFactor) {
      twoFactorChallengeToken.value = result.challengeToken;
      twoFactorMethod.value = "passkey";
      twoFactorCode.value = "";
      twoFactorRequired.value = true;
      nextTick(() => codeBoxes.value?.focus());
      return;
    }
    // Signed in on another browser: ask first
    if (result.code === "session_active") {
      sessionActiveOpen.value = true;
      nextTick(() => continueButton.value?.focus());
      return;
    }
    if (!result.success) show(result);
  } catch (error) {
    show({ code: "unreachable" });
  }
};

const continueHere = async () => {
  await handleLogin({ continueHere: true });
  sessionActiveOpen.value = false;
};

const startResend = (seconds = 60) => {
  resendIn.value = seconds;
  clearInterval(resendTimer);
  resendTimer = setInterval(() => {
    if (--resendIn.value <= 0) clearInterval(resendTimer);
  }, 1000);
};

const requestLoginOtp = async () => {
  notice.value = null;
  codeError.value = "";
  sendingCode.value = true;
  try {
    const { data } = await api.post("/auth/two-factor/send-otp", { challenge_token: twoFactorChallengeToken.value });
    maskedEmail.value = data.masked_email;
    twoFactorMethod.value = "otp";
    twoFactorCode.value = "";
    startResend(60);
    codeUntil.value = Date.now() + (data.expires_in || 180) * 1000;
    nextTick(() => codeBoxes.value?.focus());
  } catch (error) {
    if (!error.response) show({ code: "unreachable" });
    else if (error.response.data?.errors?.two_factor) expiredChallenge();
    else codeError.value = error.response.data?.errors?.otp?.[0] || error.response.data?.message || "We couldn't send a code. Try again in a minute.";
  } finally {
    sendingCode.value = false;
  }
};

// The verification step lasts five minutes; after that, sign in again
const expiredChallenge = () => {
  cancelTwoFactorLogin();
  notice.value = { tone: "info", title: "That step timed out", body: "Sign in again to get a new one." };
};

const completeTwoFactorLogin = async () => {
  if (twoFactorCode.value.length !== 6 || loading.value) return;
  if (twoFactorMethod.value === "otp" && codeUntil.value && Date.now() > codeUntil.value) {
    codeError.value = "This code has expired. Send a new one.";
    twoFactorCode.value = "";
    return;
  }
  codeError.value = "";
  notice.value = null;
  const result = await withSlowHint(() => finishTwoFactorLogin(twoFactorChallengeToken.value, twoFactorCode.value, twoFactorMethod.value));
  if (result.success) return;
  if (/verification has expired/i.test(result.error || "")) return expiredChallenge();
  if (/too many wrong passkeys/i.test(result.error || "")) {
    cancelTwoFactorLogin();
    notice.value = { tone: "lock", title: "Too many wrong passkeys", body: "Sign in again and choose to email yourself a code.", actions: ["forgot"] };
    return;
  }
  codeError.value = result.error || "That code didn't work. Check it and try again.";
  twoFactorCode.value = "";
  nextTick(() => codeBoxes.value?.focus());
};

const cancelTwoFactorLogin = () => {
  twoFactorRequired.value = false;
  twoFactorChallengeToken.value = "";
  twoFactorCode.value = "";
  twoFactorMethod.value = "passkey";
  codeError.value = "";
  clearInterval(resendTimer);
  resendIn.value = 0;
  codeUntil.value = 0;
};

// Losing the connection is shown as it happens, and cleared when it's back
const goneOffline = () => show({ code: "offline" });
const backOnline = () => {
  if (notice.value?.tone === "offline") notice.value = { tone: "success", title: "You're back online", body: "Go ahead and sign in." };
};

onMounted(() => {
  window.addEventListener("offline", goneOffline);
  window.addEventListener("online", backOnline);
  if (navigator.onLine === false) goneOffline();

  if (route.query.reset === "1") {
    notice.value = { tone: "success", title: "Password changed", body: "Sign in with your new password." };
  }
  // Shown after being signed out, e.g. when a store is suspended
  try {
    const message = sessionStorage.getItem("auth_notice");
    if (message) {
      notice.value = { tone: "warn", title: "You were signed out", body: message, actions: ["support"] };
      sessionStorage.removeItem("auth_notice");
    }
  } catch {
    // storage unavailable
  }
  nextTick(() => (form.username ? passInput.value : userInput.value)?.focus());

  // Already signed in: go straight on
  const token = getPreferredAuthToken(window.location.pathname);
  const userType = getPreferredUserType(window.location.pathname);
  const storedUser = localStorage.getItem("user");
  if (hasStoredAuthSession(window.location.pathname) && token && storedUser) {
    try {
      const userData = JSON.parse(storedUser);
      if (userType === "employee") router.push(userData.default_route || "/erp");
      else if (userData.role === "admin") router.push("/admin/dashboard");
      else if (userData.role === "vendor") router.push("/vendor/products");
      else router.push("/shop");
    } catch (e) {
      console.error("Error parsing stored user:", e);
    }
  }
});

onBeforeUnmount(() => {
  window.removeEventListener("offline", goneOffline);
  window.removeEventListener("online", backOnline);
  clearTimeout(slowTimer);
  clearInterval(resendTimer);
});
</script>

<style scoped>
.lg-forgot {
  align-self: flex-end;
  margin-top: 2px;
  font-size: 13px;
  font-weight: 500;
}
.lg-slow {
  margin-top: -6px;
}
.lg-alt {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 12px;
  font-size: 14px;
}
.lg-muted {
  color: var(--ink-soft);
}
.lg-backdrop {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: grid;
  place-items: center;
  padding: 16px;
  background: rgba(38, 45, 57, 0.5);
  backdrop-filter: blur(4px);
}
.lg-modal {
  display: flex;
  flex-direction: column;
  gap: 12px;
  width: min(440px, 100%);
  padding: 30px 28px 24px;
  border-radius: 22px;
  background: var(--paper);
  box-shadow: 0 40px 80px -30px rgba(38, 45, 57, 0.6);
}
.lg-modal h2 {
  margin: 0;
  font-size: 30px;
}
.lg-modal p {
  margin: 0 0 6px;
  font-size: 14px;
  line-height: 1.6;
  color: var(--ink-soft);
}
.lg-modal .lg-modal-note {
  margin: 6px 0 0;
  font-size: 13px;
}
</style>
