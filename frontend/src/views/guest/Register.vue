<template>
  <AuthShell :title="platform.registrations_open ? STEPS[step - 1].title : 'Sign-ups are paused'" :lead="platform.registrations_open ? STEPS[step - 1].lead : ''">
    <template #top>
      Have an account? <router-link to="/guest/login" class="af-link">Sign in</router-link>
    </template>

    <!-- Sign-ups closed by an admin (Security → Platform settings) -->
    <template v-if="!platform.registrations_open">
      <AuthNotice tone="info" title="We're not taking new customer accounts right now" body="Please check back soon. If you already have an account, you can still sign in." />
      <router-link to="/" class="af-btn ghost rg-home">Back to BloomCraft</router-link>
    </template>

    <template v-else>
      <ol class="rg-steps" aria-label="Sign-up steps">
        <li v-for="(s, i) in STEPS" :key="s.short" :class="{ on: step === i + 1, done: step > i + 1 }" :aria-current="step === i + 1 ? 'step' : undefined">
          <span class="rg-bar"></span>{{ s.short }}
        </li>
      </ol>

      <AuthNotice v-if="notice" class="rg-notice" :error="!['success', 'info'].includes(notice.tone)" :tone="notice.tone" :title="notice.title" :body="notice.body">
        <router-link v-if="notice.login" :to="{ name: 'Login', query: { u: form.email } }" class="af-link">Sign in instead</router-link>
        <router-link v-if="notice.login" :to="{ name: 'ForgotPassword', query: { u: form.email } }" class="af-link">Reset your password</router-link>
      </AuthNotice>

      <!-- Step 1: name -->
      <form v-if="step === 1" class="af-form" novalidate @submit.prevent="next">
        <div class="af-row">
          <div class="af-field">
            <label class="af-label" for="rg-first">First name</label>
            <input id="rg-first" ref="firstInput" v-model.trim="form.name" class="af-input" autocomplete="given-name" :aria-invalid="!!errors.name" @input="errors.name = ''" />
            <p v-if="errors.name" class="af-note bad">{{ errors.name }}</p>
          </div>
          <div class="af-field">
            <label class="af-label" for="rg-last">Last name</label>
            <input id="rg-last" v-model.trim="form.surname" class="af-input" autocomplete="family-name" :aria-invalid="!!errors.surname" @input="errors.surname = ''" />
            <p v-if="errors.surname" class="af-note bad">{{ errors.surname }}</p>
          </div>
        </div>
        <div class="af-field">
          <label class="af-label" for="rg-user">Username</label>
          <input
            id="rg-user"
            v-model.trim="form.username"
            class="af-input"
            autocomplete="username"
            autocapitalize="none"
            spellcheck="false"
            maxlength="40"
            :aria-invalid="!!errors.username"
            @input="onUsername"
            @blur="checkUsername"
          />
          <p v-if="errors.username" class="af-note bad">{{ errors.username }}</p>
          <p v-else-if="usernameFree" class="af-note good"><svg viewBox="0 0 24 24"><path d="m6 12.5 4 4 8-9" /></svg>@{{ form.username }} is yours if you want it</p>
          <p v-else class="af-note">Letters, numbers, - and _. You can sign in with this or your email.</p>
        </div>
        <button type="submit" class="af-btn" :class="{ busy: checking }" :disabled="checking">{{ checking ? "Checking…" : "Continue" }}</button>
      </form>

      <!-- Step 2: email + code -->
      <form v-else-if="step === 2" class="af-form" novalidate @submit.prevent="next">
        <div class="af-field">
          <label class="af-label" for="rg-email">
            Email
            <button v-if="codeSent" type="button" class="af-plain rg-small" @click="changeEmail">Change</button>
          </label>
          <input
            id="rg-email"
            ref="emailInput"
            v-model.trim="form.email"
            class="af-input"
            type="email"
            autocomplete="email"
            inputmode="email"
            :disabled="codeSent"
            :aria-invalid="!!errors.email"
            @input="errors.email = ''"
          />
          <p v-if="errors.email" class="af-note bad">{{ errors.email }}</p>
        </div>

        <template v-if="codeSent">
          <div class="af-field">
            <span class="af-label">6-digit code</span>
            <CodeBoxes ref="boxes" v-model="form.otp" :invalid="!!errors.otp" @update:model-value="errors.otp = ''" @complete="next" />
            <p v-if="errors.otp" class="af-note bad" role="alert">{{ errors.otp }}</p>
            <CodeTimer v-else-if="codeUntil" :until="codeUntil" />
            <p class="af-note">
              We sent it to {{ form.email }}. Not there? Check spam, or
              <button type="button" class="af-plain" :disabled="sending || resendIn > 0" @click="sendCode">{{ resendIn > 0 ? `send another in ${resendIn}s` : "send another" }}</button>.
            </p>
          </div>
          <button type="submit" class="af-btn">Continue</button>
        </template>
        <button v-else type="button" class="af-btn" :class="{ busy: sending }" :disabled="sending" @click="sendCode">{{ sending ? "Sending the code…" : "Email me a code" }}</button>
        <button type="button" class="af-plain rg-back" @click="step = 1">Back</button>
      </form>

      <!-- Step 3: password -->
      <form v-else class="af-form" novalidate @submit.prevent="submit">
        <CodeTimer v-if="codeUntil" :until="codeUntil" class="rg-step-timer" />
        <div class="af-field">
          <label class="af-label" for="rg-pass">Password</label>
          <PasswordField id="rg-pass" ref="passInput" v-model="form.password" autocomplete="new-password" :invalid="!!errors.password" @update:model-value="errors.password = ''" />
          <PasswordRules :password="form.password" />
          <p v-if="errors.password" class="af-note bad">{{ errors.password }}</p>
        </div>
        <div class="af-field">
          <label class="af-label" for="rg-confirm">Type it again</label>
          <PasswordField id="rg-confirm" v-model="form.password_confirmation" autocomplete="new-password" :invalid="!!errors.password_confirmation" @update:model-value="errors.password_confirmation = ''" />
          <p v-if="errors.password_confirmation" class="af-note bad">{{ errors.password_confirmation }}</p>
          <p v-else-if="form.password_confirmation && form.password_confirmation === form.password" class="af-note good"><svg viewBox="0 0 24 24"><path d="m6 12.5 4 4 8-9" /></svg>Passwords match</p>
        </div>
        <button type="submit" class="af-btn" :class="{ busy: loading }" :disabled="loading">{{ loading ? "Creating your account…" : "Create account" }}</button>
        <button type="button" class="af-plain rg-back" :disabled="loading" @click="step = 2">Back</button>
      </form>

      <p class="af-foot">Selling flowers? <router-link to="/guest/vendor_register" class="af-link">Apply as a vendor</router-link> instead.</p>
    </template>
  </AuthShell>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from "vue";
import AuthShell from "../../components/auth/AuthShell.vue";
import AuthNotice from "../../components/auth/AuthNotice.vue";
import PasswordField from "../../components/auth/PasswordField.vue";
import PasswordRules from "../../components/auth/PasswordRules.vue";
import CodeBoxes from "../../components/auth/CodeBoxes.vue";
import CodeTimer from "../../components/auth/CodeTimer.vue";
import { useAuth } from "../../composables/useAuth";
import { usePlatformStatus } from "../../composables/usePlatformStatus";
import { passwordOk } from "../../utils/passwordRules";
import api from "../../plugins/axios";

const STEPS = [
  { short: "Your name", title: "Create your account", lead: "Order bouquets from local florists, or design your own in 3D." },
  { short: "Your email", title: "Confirm your email", lead: "We'll email a 6-digit code to make sure the address is yours." },
  { short: "Password", title: "Choose a password", lead: "Almost done. You'll sign in with your username or email and this password." },
];

const { register, loading } = useAuth();
const { status: platform, refresh: refreshPlatform } = usePlatformStatus();

const step = ref(1);
const form = reactive({ name: "", surname: "", username: "", email: "", otp: "", password: "", password_confirmation: "" });
const errors = reactive({});
const notice = ref(null);
const checking = ref(false);
const sending = ref(false);
const codeSent = ref(false);
const resendIn = ref(0);
// When the emailed code stops working
const codeUntil = ref(0);
const usernameFree = ref(false);
const firstInput = ref(null);
const emailInput = ref(null);
const passInput = ref(null);
const boxes = ref(null);
let timer = null;

const unreachable = { tone: "offline", title: "BloomCraft didn't answer", body: "The server may still be waking up or your connection dropped. Wait a moment, then try again." };
const FIELD_STEP = { name: 1, surname: 1, username: 1, email: 2, otp: 2, password: 3, password_confirmation: 3 };

watch(step, (s) => {
  notice.value = null;
  nextTick(() => (s === 1 ? firstInput : s === 2 ? (codeSent.value ? boxes : emailInput) : passInput).value?.focus());
});

function countdown(seconds) {
  resendIn.value = seconds;
  clearInterval(timer);
  timer = setInterval(() => {
    if (--resendIn.value <= 0) clearInterval(timer);
  }, 1000);
}

// Username: format first, then ask the server whether it's free
let usernameTimer = null;
function onUsername() {
  errors.username = "";
  usernameFree.value = false;
  clearTimeout(usernameTimer);
  usernameTimer = setTimeout(checkUsername, 600);
}
async function checkUsername() {
  clearTimeout(usernameTimer);
  const u = form.username;
  if (!u) return true;
  if (!/^[A-Za-z0-9_-]+$/.test(u)) {
    errors.username = "Use only letters, numbers, - and _ (no spaces).";
    return false;
  }
  try {
    const { data } = await api.post("/auth/check-availability", { username: u });
    if (u !== form.username) return true;
    usernameFree.value = data.available?.username === true;
    if (data.available?.username === false) errors.username = `@${u} is taken. Try adding a number or your last name.`;
    return data.available?.username !== false;
  } catch {
    return true; // the final step checks again
  }
}

async function next() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  if (step.value === 1) {
    if (!form.name) errors.name = "Enter your first name.";
    if (!form.surname) errors.surname = "Enter your last name.";
    if (!form.username) errors.username = "Choose a username.";
    if (Object.keys(errors).length) return;
    checking.value = true;
    const ok = await checkUsername();
    checking.value = false;
    if (ok && !errors.username) step.value = 2;
    return;
  }
  if (step.value === 2) {
    if (form.otp.length !== 6) {
      errors.otp = "Enter all 6 digits from the email.";
      return;
    }
    if (codeUntil.value && Date.now() > codeUntil.value) {
      errors.otp = "This code has expired. Send a new one.";
      form.otp = "";
      return;
    }
    step.value = 3;
  }
}

async function sendCode() {
  errors.email = "";
  notice.value = null;
  if (!/^\S+@\S+\.\S+$/.test(form.email)) {
    errors.email = "Enter a valid email, like name@example.com.";
    emailInput.value?.focus();
    return;
  }
  sending.value = true;
  try {
    const { data } = await api.post("/auth/send-otp", { email: form.email });
    codeSent.value = true;
    form.otp = "";
    countdown(data.retry_after || 60);
    codeUntil.value = Date.now() + (data.expires_in || 180) * 1000;
    nextTick(() => boxes.value?.focus());
  } catch (e) {
    const res = e.response;
    if (!res) notice.value = unreachable;
    else if (res.status === 403) refreshPlatform();
    else if (res.data?.errors?.email) {
      errors.email = res.data.errors.email[0];
      if (/already has an account/i.test(errors.email)) notice.value = { tone: "info", title: "You already have an account", body: "This email is registered. Sign in, or reset your password if you can't remember it.", login: true };
    } else if (res.status === 429) {
      codeSent.value = true;
      countdown(res.data?.retry_after || 60);
      notice.value = { tone: "lock", title: "A code is already on its way", body: res.data?.message || "Wait a moment before asking for another." };
    } else notice.value = { tone: "warn", title: "We couldn't send the email", body: res.data?.message || "Please try again in a few minutes." };
  } finally {
    sending.value = false;
  }
}

function changeEmail() {
  codeSent.value = false;
  codeUntil.value = 0;
  form.otp = "";
  clearInterval(timer);
  resendIn.value = 0;
  nextTick(() => emailInput.value?.focus());
}

async function submit() {
  Object.keys(errors).forEach((k) => delete errors[k]);
  if (!passwordOk(form.password)) errors.password = "Your password needs every item on the list.";
  if (form.password_confirmation !== form.password) errors.password_confirmation = "These don't match yet.";
  if (Object.keys(errors).length) return;
  // The code ran out while choosing a password: back for a new one
  if (codeUntil.value && Date.now() > codeUntil.value) {
    form.otp = "";
    step.value = 2;
    await nextTick();
    errors.otp = "This code has expired. Send a new one.";
    return;
  }

  const result = await register(form);
  if (result.success) return; // register() takes them to the shop

  const list = result.errors ?? {};
  for (const [k, v] of Object.entries(list)) errors[k] = Array.isArray(v) ? v[0] : v;
  const first = Object.keys(list).map((k) => FIELD_STEP[k]).filter(Boolean).sort()[0];
  if (first && first !== step.value) {
    // A wrong or expired code needs a fresh one
    if (list.otp) form.otp = "";
    const kept = { ...errors };
    step.value = first;
    await nextTick();
    Object.assign(errors, kept);
  } else if (!Object.keys(list).length) {
    notice.value = result.error && !/registration failed/i.test(result.error) ? { tone: "warn", title: "We couldn't create your account", body: result.error } : unreachable;
  }
}

onMounted(() => {
  refreshPlatform();
  nextTick(() => firstInput.value?.focus());
});
onBeforeUnmount(() => {
  clearInterval(timer);
  clearTimeout(usernameTimer);
});
</script>

<style scoped>
.rg-steps {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin: -8px 0 28px;
  padding: 0;
  list-style: none;
  font-size: 13px;
  color: var(--ink-soft);
}
.rg-bar {
  display: block;
  height: 3px;
  margin-bottom: 8px;
  border-radius: 2px;
  background: var(--line);
  transition: background 0.3s;
}
.rg-steps .done .rg-bar {
  background: var(--leaf-deep);
}
.rg-steps .on {
  color: var(--ink);
  font-weight: 500;
}
.rg-steps .on .rg-bar {
  background: linear-gradient(90deg, var(--leaf-deep) 50%, var(--line) 50%);
}
.rg-step-timer {
  margin: -8px 0 0;
}
.rg-notice {
  margin-bottom: 22px;
}
.rg-small {
  font-size: 13px;
  font-weight: 400;
}
.rg-back {
  align-self: flex-start;
  font-size: 14px;
  color: var(--ink-soft);
}
.rg-home {
  margin-top: 20px;
  text-decoration: none;
}
</style>
