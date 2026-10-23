<template>
  <AuthShell :title="step === 1 ? 'Reset your password' : 'Choose a new password'" :lead="lead">
    <template #top>
      Remembered it? <router-link :to="{ name: 'Login', query: { u: identifier || undefined } }" class="af-link">Sign in</router-link>
    </template>

    <AuthNotice v-if="notice" class="fp-notice" :error="notice.tone !== 'success'" :tone="notice.tone" :title="notice.title" :body="notice.body" />

    <!-- Step 1: who are you -->
    <form v-if="step === 1" class="af-form" novalidate @submit.prevent="sendCode">
      <div class="af-field">
        <label class="af-label" for="fp-id">Username or email</label>
        <input
          id="fp-id"
          ref="idInput"
          v-model.trim="identifier"
          class="af-input"
          type="text"
          autocomplete="username"
          autocapitalize="none"
          spellcheck="false"
          :aria-invalid="idError ? 'true' : 'false'"
          @input="idError = ''"
        />
        <p v-if="idError" class="af-note bad">{{ idError }}</p>
      </div>
      <button type="submit" class="af-btn" :class="{ busy: sending }" :disabled="sending">{{ sending ? "Sending the code…" : "Email me a code" }}</button>
      <p class="af-note">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" /><path d="M12 11v5M12 8h.01" /></svg>
        Shop staff who sign in to the ERP: ask your shop owner to reset your password from Employees.
      </p>
    </form>

    <!-- Step 2: code + new password -->
    <form v-else class="af-form" novalidate @submit.prevent="reset">
      <div class="af-field">
        <span class="af-label">6-digit code</span>
        <CodeBoxes ref="boxes" v-model="otp" :invalid="!!errors.otp" :disabled="saving" @update:model-value="errors.otp = ''" @complete="focusPassword" />
        <p v-if="errors.otp" class="af-note bad" role="alert">{{ errors.otp }}</p>
        <CodeTimer v-else-if="codeUntil" :until="codeUntil" />
        <p class="af-note">
          Didn't get it? Check spam, or
          <button type="button" class="af-plain" :disabled="sending || resendIn > 0" @click="sendCode">{{ resendIn > 0 ? `send another in ${resendIn}s` : "send another" }}</button>.
        </p>
      </div>
      <div class="af-field">
        <label class="af-label" for="fp-pass">New password</label>
        <PasswordField id="fp-pass" ref="passInput" v-model="password" autocomplete="new-password" :invalid="!!errors.password" @update:model-value="errors.password = ''" />
        <PasswordRules :password="password" />
        <p v-if="errors.password" class="af-note bad">{{ errors.password }}</p>
      </div>
      <div class="af-field">
        <label class="af-label" for="fp-confirm">Type it again</label>
        <PasswordField id="fp-confirm" v-model="confirm" autocomplete="new-password" :invalid="!!errors.confirm" @update:model-value="errors.confirm = ''" />
        <p v-if="errors.confirm" class="af-note bad">{{ errors.confirm }}</p>
        <p v-else-if="confirm && confirm === password" class="af-note good">
          <svg viewBox="0 0 24 24"><path d="m6 12.5 4 4 8-9" /></svg>Passwords match
        </p>
      </div>
      <button type="submit" class="af-btn" :class="{ busy: saving }" :disabled="saving">{{ saving ? "Saving…" : "Save new password" }}</button>
      <button type="button" class="af-plain fp-back" :disabled="saving" @click="step = 1">Use a different username or email</button>
    </form>
  </AuthShell>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AuthShell from "../../components/auth/AuthShell.vue";
import AuthNotice from "../../components/auth/AuthNotice.vue";
import PasswordField from "../../components/auth/PasswordField.vue";
import PasswordRules from "../../components/auth/PasswordRules.vue";
import CodeBoxes from "../../components/auth/CodeBoxes.vue";
import CodeTimer from "../../components/auth/CodeTimer.vue";
import { passwordOk } from "../../utils/passwordRules";
import api from "../../plugins/axios";

const route = useRoute();
const router = useRouter();

const step = ref(1);
const identifier = ref(typeof route.query.u === "string" ? route.query.u : "");
const idError = ref("");
const otp = ref("");
const password = ref("");
const confirm = ref("");
const errors = reactive({ otp: "", password: "", confirm: "" });
const notice = ref(null);
const sending = ref(false);
const saving = ref(false);
const resendIn = ref(0);
const codeUntil = ref(0);
const idInput = ref(null);
const passInput = ref(null);
const boxes = ref(null);
let timer = null;

const lead = computed(() =>
  step.value === 1
    ? "Tell us the username or email on your account. We'll email a 6-digit code so you can choose a new password."
    : "Enter the code from the email, then pick a password you haven't used here before.",
);

const unreachable = { tone: "offline", title: "BloomCraft didn't answer", body: "The server may still be waking up or your connection dropped. Wait a moment, then try again." };

function countdown(seconds) {
  resendIn.value = seconds;
  clearInterval(timer);
  timer = setInterval(() => {
    if (--resendIn.value <= 0) clearInterval(timer);
  }, 1000);
}

async function sendCode() {
  if (!identifier.value) {
    idError.value = "Enter your username or email.";
    idInput.value?.focus();
    return;
  }
  sending.value = true;
  notice.value = null;
  try {
    const { data } = await api.post("/auth/password/forgot", { identifier: identifier.value });
    notice.value = { tone: "success", title: "Check your email", body: data.message };
    step.value = 2;
    countdown(data.retry_after || 60);
    codeUntil.value = Date.now() + (data.expires_in || 180) * 1000;
    nextTick(() => boxes.value?.focus());
  } catch (e) {
    if (!e.response) notice.value = unreachable;
    else if (e.response.status === 429) notice.value = { tone: "lock", title: "Slow down a little", body: "Too many requests in a short time. Wait a minute, then try again." };
    else notice.value = { tone: "warn", title: "We couldn't send the email", body: e.response.data?.message || "Please try again in a few minutes." };
  } finally {
    sending.value = false;
  }
}

function focusPassword() {
  passInput.value?.focus();
}

async function reset() {
  errors.otp = otp.value.length !== 6 ? "Enter all 6 digits from the email." : codeUntil.value && Date.now() > codeUntil.value ? "This code has expired. Send a new one." : "";
  errors.password = passwordOk(password.value) ? "" : "Your new password needs every item on the list.";
  errors.confirm = confirm.value === password.value ? "" : "These don't match yet.";
  if (errors.otp || errors.password || errors.confirm) return;

  saving.value = true;
  notice.value = null;
  try {
    const { data } = await api.post("/auth/password/reset", { identifier: identifier.value, otp: otp.value, password: password.value, password_confirmation: confirm.value });
    router.replace({ name: "Login", query: { u: data.username || identifier.value, reset: "1" } });
  } catch (e) {
    if (!e.response) {
      notice.value = unreachable;
      return;
    }
    const list = e.response.data?.errors ?? {};
    errors.otp = list.otp?.[0] ?? "";
    errors.password = list.password?.[0] ?? "";
    if (errors.otp) {
      otp.value = "";
      nextTick(() => boxes.value?.focus());
    }
    if (!errors.otp && !errors.password) {
      notice.value = e.response.status === 429
        ? { tone: "lock", title: "Slow down a little", body: "Too many tries in a short time. Wait a minute, then try again." }
        : { tone: "warn", title: "We couldn't save it", body: e.response.data?.message || "Please try again." };
    }
  } finally {
    saving.value = false;
  }
}

onMounted(() => nextTick(() => idInput.value?.focus()));
onBeforeUnmount(() => clearInterval(timer));
</script>

<style scoped>
.fp-notice {
  margin-bottom: 22px;
}
.fp-back {
  align-self: flex-start;
  font-size: 14px;
  color: var(--ink-soft);
}
</style>
