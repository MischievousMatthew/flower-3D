<template>
  <div class="register-container">
    <!-- Logo -->
    <router-link to="/" class="logo">
      <img
        src="../../../public/bloomcraft-blankBg.png"
        alt="Bloomcraft Logo"
        width="60"
        height="60"
      />
    </router-link>

    <!-- Back Button -->
    <button v-if="currentStep > 1" @click="previousStep" class="back-btn">
      ← Back
    </button>

    <!-- Header -->
    <div class="header">
      <h1 class="title">Create an account</h1>
      <p class="subtitle">
        Already have an account?
        <router-link to="/guest/login" class="link">Log in</router-link>
      </p>
    </div>

    <!-- Progress Steps -->
    <div class="steps-indicator">
      <div
        class="step"
        :class="{ active: currentStep >= 1, completed: currentStep > 1 }"
      >
        <div class="step-number">1</div>
        <span class="step-label">Personal Info</span>
      </div>
      <div class="step-line" :class="{ active: currentStep >= 2 }"></div>
      <div
        class="step"
        :class="{ active: currentStep >= 2, completed: currentStep > 2 }"
      >
        <div class="step-number">2</div>
        <span class="step-label">Email & OTP</span>
      </div>
      <div class="step-line" :class="{ active: currentStep >= 3 }"></div>
      <div class="step" :class="{ active: currentStep >= 3 }">
        <div class="step-number">3</div>
        <span class="step-label">Create Password</span>
      </div>
    </div>

    <!-- Form Content -->
    <div class="form-content">
      <!-- Error Alert -->
      <div v-if="errorMessage" class="alert-error">
        {{ errorMessage }}
      </div>

      <form @submit.prevent="handleNext" class="register-form">
        <!-- Step 1: Personal Information -->
        <div v-show="currentStep === 1" class="form-step">
          <h2 class="step-title">What's your name?</h2>

          <div class="input-group">
            <label class="input-label">First Name</label>
            <input
              v-model="form.name"
              type="text"
              :required="currentStep === 1"
              placeholder="Enter your first name"
              class="form-input"
              :class="{ 'input-error': errors.name }"
            />
            <span v-if="errors.name" class="error-msg">{{ errors.name }}</span>
          </div>

          <div class="input-group">
            <label class="input-label">Last Name</label>
            <input
              v-model="form.surname"
              type="text"
              :required="currentStep === 1"
              placeholder="Enter your last name"
              class="form-input"
              :class="{ 'input-error': errors.surname }"
            />
            <span v-if="errors.surname" class="error-msg">{{
              errors.surname
            }}</span>
          </div>

          <div class="input-group">
            <label class="input-label">Username</label>
            <input
              v-model="form.username"
              type="text"
              :required="currentStep === 1"
              placeholder="Choose a username"
              class="form-input"
              :class="{ 'input-error': errors.username }"
            />
            <span v-if="errors.username" class="error-msg">{{
              errors.username
            }}</span>
          </div>
        </div>

        <!-- Step 2: Email & OTP -->
        <div v-show="currentStep === 2" class="form-step">
          <h2 class="step-title">Verify your email address</h2>

          <div class="input-group">
            <label class="input-label">Email Address</label>
            <input
              v-model="form.email"
              type="email"
              placeholder="Enter your email address"
              :required="currentStep === 2"
              class="form-input"
              :class="{ 'input-error': errors.email }"
              :disabled="otpSent"
            />
            <span v-if="errors.email" class="error-msg">{{
              errors.email
            }}</span>
          </div>

          <button
            type="button"
            @click="handleSendOtp"
            :disabled="!canResend || otpLoading || !form.email"
            class="otp-btn"
          >
            <span v-if="otpLoading">Sending...</span>
            <span v-else-if="!canResend">Resend in {{ cooldown }}s</span>
            <span v-else>{{ otpSent ? "Resend OTP" : "Send OTP Code" }}</span>
          </button>

          <!-- OTP field only shown after OTP is sent -->
          <div v-if="otpSent" class="input-group">
            <label class="input-label">Enter OTP Code</label>
            <p class="hint-text" style="margin-bottom: 10px">
              We sent a 6-digit code to <strong>{{ form.email }}</strong>
            </p>
            <input
              v-model="form.otp"
              type="text"
              maxlength="6"
              placeholder="000000"
              :required="currentStep === 2"
              class="form-input otp-input"
              :class="{ 'input-error': errors.otp }"
              autocomplete="one-time-code"
            />
            <span v-if="errors.otp" class="error-msg">{{ errors.otp }}</span>
          </div>

          <!-- Change email link -->
          <p v-if="otpSent" class="change-email-link">
            Wrong email?
            <button type="button" @click="resetEmail" class="link-btn">
              Change it
            </button>
          </p>
        </div>

        <!-- Step 3: Create Password -->
        <div v-show="currentStep === 3" class="form-step">
          <h2 class="step-title">Create your password</h2>

          <div class="input-group">
            <label class="input-label">Password</label>
            <input
              v-model="form.password"
              type="password"
              :required="currentStep === 3"
              minlength="8"
              placeholder="Minimum 8 characters"
              class="form-input"
              :class="{ 'input-error': errors.password }"
            />
            <p class="hint-text">
              Must include uppercase, lowercase, and number
            </p>
            <span v-if="errors.password" class="error-msg">{{
              errors.password
            }}</span>
          </div>

          <div class="input-group">
            <label class="input-label">Confirm Password</label>
            <input
              v-model="form.password_confirmation"
              type="password"
              :required="currentStep === 3"
              placeholder="Re-enter your password"
              class="form-input"
              :class="{ 'input-error': errors.password_confirmation }"
            />
            <span v-if="errors.password_confirmation" class="error-msg">
              {{ errors.password_confirmation }}
            </span>
          </div>
        </div>

        <!-- Action Button -->
        <button v-if="currentStep < 3" type="submit" class="action-btn">
          Next
        </button>

        <button
          v-else
          type="button"
          @click="handleRegister"
          :disabled="loading"
          class="action-btn"
        >
          <span v-if="loading">Creating Account...</span>
          <span v-else>Create Account</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../../composables/useAuth";
import axios from "axios";

const router = useRouter();
const { register, loading } = useAuth();

const currentStep = ref(1);
const otpLoading = ref(false);
const otpSent = ref(false);
const cooldown = ref(0);
let cooldownTimer = null;

const form = reactive({
  name: "",
  surname: "",
  username: "",
  email: "",
  otp: "",
  password: "",
  password_confirmation: "",
});

const errors = ref({});
const errorMessage = ref("");

// ── cooldown timer ───────────────────────────────────────────
function startCooldown(seconds = 60) {
  cooldown.value = seconds;
  clearInterval(cooldownTimer);
  cooldownTimer = setInterval(() => {
    if (--cooldown.value <= 0) clearInterval(cooldownTimer);
  }, 1000);
}

const canResend = computed(() => cooldown.value === 0);

// ── reset email (let user correct it) ───────────────────────
function resetEmail() {
  otpSent.value = false;
  form.otp = "";
  errors.value = {};
  errorMessage.value = "";
  clearInterval(cooldownTimer);
  cooldown.value = 0;
}

// ── send OTP ─────────────────────────────────────────────────
async function handleSendOtp() {
  errors.value = {};
  errorMessage.value = "";

  if (!form.email) {
    errors.value.email = "Email address is required.";
    return;
  }

  otpLoading.value = true;
  try {
    await axios.post("/api/auth/send-otp", { email: form.email });
    otpSent.value = true;
    startCooldown(60);
  } catch (err) {
    console.log("422 details:", err.response?.data); // ← ADD THIS
    if (err.response?.data?.errors?.email) {
      errors.value.email = err.response.data.errors.email[0];
    } else {
      errorMessage.value =
        err.response?.data?.message ?? "Failed to send OTP. Please try again.";
    }
  } finally {
    otpLoading.value = false;
  }
}

// ── step validation ──────────────────────────────────────────
function validateStep() {
  errors.value = {};
  errorMessage.value = "";

  if (currentStep.value === 1) {
    if (!form.name) errors.value.name = "First name is required.";
    if (!form.surname) errors.value.surname = "Last name is required.";
    if (!form.username) errors.value.username = "Username is required.";
    return Object.keys(errors.value).length === 0;
  }

  if (currentStep.value === 2) {
    if (!form.email) {
      errors.value.email = "Email address is required.";
      return false;
    }
    if (!otpSent.value) {
      errorMessage.value = "Please send the OTP code to your email first.";
      return false;
    }
    if (!form.otp) {
      errors.value.otp = "Please enter the OTP code.";
      return false;
    }
    if (form.otp.length !== 6) {
      errors.value.otp = "OTP must be 6 digits.";
      return false;
    }
    return true;
  }

  if (currentStep.value === 3) {
    if (!form.password) {
      errors.value.password = "Password is required.";
      return false;
    }
    if (form.password.length < 8) {
      errors.value.password = "Password must be at least 8 characters.";
      return false;
    }
    if (form.password !== form.password_confirmation) {
      errors.value.password_confirmation = "Passwords do not match.";
      return false;
    }
    return true;
  }

  return true;
}

function handleNext() {
  if (validateStep() && currentStep.value < 3) currentStep.value++;
}

function previousStep() {
  if (currentStep.value > 1) {
    currentStep.value--;
    errors.value = {};
    errorMessage.value = "";
  }
}

// ── final register ───────────────────────────────────────────
async function handleRegister() {
  if (!validateStep()) return;

  try {
    const result = await register(form);
    if (result.success) {
      router.push("/shop");
    } else {
      if (result.errors) {
        errors.value = Object.fromEntries(
          Object.entries(result.errors).map(([k, v]) => [k, v[0]]),
        );
      }
      errorMessage.value =
        result.error ?? "Registration failed. Please try again.";
    }
  } catch {
    errorMessage.value = "Failed to create account. Please try again.";
  }
}
</script>

<style scoped>
.register-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #ffffff;
  padding: 40px 20px;
  position: relative;
}

.logo {
  text-decoration: none;
  position: absolute;
  top: 32px;
  left: 32px;
  animation: bounce 2s ease-in-out infinite;
}

@keyframes bounce {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-6px);
  }
}

.back-btn {
  position: absolute;
  top: 32px;
  right: 32px;
  background: none;
  border: none;
  color: #4a5568;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  padding: 8px 12px;
  transition: all 0.2s;
}

.back-btn:hover {
  color: #2d3748;
  transform: translateX(-4px);
}

.header {
  text-align: center;
  margin-bottom: 48px;
}

.title {
  font-size: 32px;
  font-weight: 400;
  color: #2d3748;
  margin-bottom: 12px;
  letter-spacing: -0.5px;
}

.subtitle {
  font-size: 14px;
  color: #718096;
}

.link {
  color: #2d3748;
  text-decoration: underline;
  transition: color 0.2s;
}

.link:hover {
  color: #000000;
}

.steps-indicator {
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 48px;
  max-width: 500px;
  width: 100%;
}

.step {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  flex: 0 0 auto;
}

.step-number {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: #e2e8f0;
  color: #a0aec0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 500;
  font-size: 14px;
  transition: all 0.3s ease;
}

.step.active .step-number {
  background: #2d3748;
  color: white;
}
.step.completed .step-number {
  background: #20734d;
  color: white;
}

.step-label {
  font-size: 11px;
  color: #a0aec0;
  white-space: nowrap;
}

.step.active .step-label {
  color: #2d3748;
  font-weight: 500;
}

.step-line {
  flex: 1;
  height: 1px;
  background: #e2e8f0;
  margin: 0 12px;
  transition: all 0.3s ease;
  min-width: 40px;
  max-width: 100px;
}

.step-line.active {
  background: #cbd5e0;
}

.form-content {
  width: 100%;
  max-width: 480px;
}

.alert-error {
  padding: 12px 16px;
  background: #fee2e2;
  color: #dc2626;
  border: 1px solid #fca5a5;
  border-radius: 8px;
  margin-bottom: 24px;
  font-size: 14px;
  text-align: center;
}

.register-form {
  width: 100%;
}

.form-step {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.step-title {
  font-size: 18px;
  font-weight: 400;
  color: #2d3748;
  margin-bottom: 24px;
  text-align: center;
}

.input-group {
  margin-bottom: 20px;
}

.input-label {
  display: block;
  font-size: 14px;
  color: #2d3748;
  margin-bottom: 8px;
}

.form-input {
  width: 100%;
  padding: 12px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 15px;
  color: #2d3748;
  background: #ffffff;
  transition: all 0.2s ease;
  box-sizing: border-box;
}

.form-input:focus {
  outline: none;
  border-color: #cbd5e0;
}

.form-input::placeholder {
  color: #cbd5e0;
}

.form-input:disabled {
  background: #f7fafc;
  color: #a0aec0;
  cursor: not-allowed;
}

.otp-input {
  font-size: 24px;
  letter-spacing: 10px;
  text-align: center;
  font-weight: 500;
}

.input-error {
  border-color: #f56565 !important;
}

.error-msg {
  display: block;
  color: #e53e3e;
  font-size: 13px;
  margin-top: 6px;
}

.hint-text {
  font-size: 12px;
  color: #a0aec0;
  margin-top: 6px;
}

.otp-btn {
  width: 100%;
  padding: 12px;
  background: #f7fafc;
  color: #2d3748;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  margin-bottom: 20px;
}

.otp-btn:hover:not(:disabled) {
  background: #edf2f7;
  border-color: #cbd5e0;
}

.otp-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.change-email-link {
  text-align: center;
  font-size: 13px;
  color: #718096;
  margin-top: 12px;
}

.link-btn {
  background: none;
  border: none;
  color: #2d3748;
  font-size: 13px;
  text-decoration: underline;
  cursor: pointer;
  padding: 0;
}

.link-btn:hover {
  color: #000000;
}

.action-btn {
  width: 100%;
  padding: 14px;
  background: #2d3748;
  color: white;
  border: none;
  border-radius: 6px;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  margin-top: 24px;
}

.action-btn:hover:not(:disabled) {
  background: #1a202c;
}
.action-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .logo {
    top: 20px;
    left: 20px;
  }
  .back-btn {
    top: 20px;
    right: 20px;
  }
  .title {
    font-size: 28px;
  }
  .step-label {
    font-size: 10px;
  }
  .step-number {
    width: 28px;
    height: 28px;
    font-size: 12px;
  }
  .step-line {
    margin: 0 8px;
    min-width: 20px;
  }
}
</style>
