<template>
  <div class="login-container">
    <!-- Logo -->
    <router-link to="/" class="logo"
      ><img
        src="../../../public/bloomcraft-blankBg.png"
        alt="Bloomcraft Logo"
        width="60"
        height="60"
    /></router-link>

    <!-- Header -->
    <div class="header">
      <h1 class="title">Welcome back</h1>
      <p class="subtitle">
        Don't have an account?
        <router-link to="/guest/register" class="link">Sign up</router-link>
      </p>
    </div>

    <!-- Form Content -->
    <div class="form-content">
      <form @submit.prevent="handleLogin" class="login-form">
        <!-- Error Alert -->
        <div v-if="errorMessage" class="alert-error">
          {{ errorMessage }}
        </div>

        <!-- Username Input -->
        <div class="input-group">
          <label class="input-label">Username</label>
          <input
            v-model="form.username"
            type="text"
            required
            placeholder="Enter your username"
            autocomplete="username"
            class="form-input"
            :class="{ 'input-error': errors.username }"
          />
          <span v-if="errors.username" class="error-msg">{{
            errors.username
          }}</span>
        </div>

        <!-- Password Input -->
        <div class="input-group">
          <label class="input-label">Password</label>
          <div class="password-field">
            <input
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              required
              placeholder="Enter your password"
              autocomplete="current-password"
              class="form-input"
              :class="{ 'input-error': errors.password }"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="toggle-password"
              tabindex="-1"
            >
              {{ showPassword ? "😌" : "🫣" }}
            </button>
          </div>
          <span v-if="errors.password" class="error-msg">{{
            errors.password
          }}</span>
        </div>

        <!-- Login Button -->
        <button type="submit" :disabled="loading" class="login-btn">
          <span v-if="loading">Signing in...</span>
          <span v-else>Log in</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../../composables/useAuth";

const router = useRouter();
const { combinedLogin, loading } = useAuth();

const form = reactive({
  username: "",
  password: "",
});

const errors = ref({});
const errorMessage = ref("");
const showPassword = ref(false);

const handleLogin = async () => {
  errors.value = {};
  errorMessage.value = "";

  try {
    const result = await combinedLogin(form);

    if (!result.success) {
      if (result.errors) {
        errors.value = result.errors;
      } else {
        errorMessage.value = result.error || "Login failed";
      }
    }
  } catch (error) {
    console.error("Login error:", error);
    errorMessage.value = "An error occurred. Please try again.";
  }
};

onMounted(() => {
  // Check if user is already logged in
  const token = localStorage.getItem("auth_token");
  const storedUser = localStorage.getItem("user");

  if (token && storedUser) {
    try {
      const userData = JSON.parse(storedUser);
      // Redirect based on role
      if (userData.role === "admin") {
        router.push("/admin/vendor-requests");
      } else if (userData.role === "vendor") {
        router.push("/vendor/products");
      } else {
        router.push("/shop");
      }
    } catch (e) {
      console.error("Error parsing stored user:", e);
    }
  }
});
</script>

<style scoped>
.login-container {
  min-height: 85vh;
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
  font-size: 32px;
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
  font-weight: 400;
}

.link {
  color: #2d3748;
  text-decoration: underline;
  font-weight: 400;
  transition: color 0.2s;
}

.link:hover {
  color: #000000;
}

.form-content {
  width: 100%;
  max-width: 400px;
}

.login-form {
  width: 100%;
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

.input-group {
  margin-bottom: 20px;
}

.input-label {
  display: block;
  font-size: 14px;
  font-weight: 400;
  color: #2d3748;
  margin-bottom: 8px;
}

.form-input {
  width: 90%;
  padding: 12px 16px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 15px;
  color: #2d3748;
  background: #ffffff;
  transition: all 0.2s ease;
}

.form-input:focus {
  outline: none;
  border-color: #cbd5e0;
}

.form-input::placeholder {
  color: #cbd5e0;
}

.password-field {
  position: relative;
}

.toggle-password {
  position: absolute;
  right: 12px;
  top: 50%;
  transform: translateY(-50%);
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  opacity: 0.5;
  transition: opacity 0.2s;
  padding: 4px;
}

.toggle-password:hover {
  opacity: 0.8;
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

.login-btn {
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
  margin-top: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

.login-btn:hover:not(:disabled) {
  background: #1a202c;
}

.login-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.login-type-indicator {
  margin-bottom: 20px;
  padding: 10px 15px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 8px;
  text-align: center;
}

.employee-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: white;
  font-size: 14px;
  font-weight: 500;
}

.employee-badge svg {
  fill: white;
}

.login-type-toggle {
  text-align: center;
  margin: 20px 0;
}

.toggle-link {
  background: none;
  border: none;
  color: #667eea;
  font-size: 14px;
  cursor: pointer;
  text-decoration: underline;
  padding: 0;
}

.toggle-link:hover {
  color: #5568d3;
}

.loading-spinner {
  animation: spin 1s linear infinite;
  display: inline-block;
  margin-right: 8px;
  vertical-align: middle;
}

@keyframes spin {
  0% {
    transform: rotate(0deg);
  }
  100% {
    transform: rotate(360deg);
  }
}

.divider {
  position: relative;
  text-align: center;
  margin: 32px 0;
}

.divider::before {
  content: "";
  position: absolute;
  left: 0;
  top: 50%;
  width: 100%;
  height: 1px;
  background: #e2e8f0;
}

.divider-text {
  position: relative;
  background: white;
  padding: 0 16px;
  color: #a0aec0;
  font-size: 13px;
  font-weight: 400;
}

.social-buttons {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.social-btn {
  width: 100%;
  padding: 12px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  background: white;
  color: #2d3748;
}

.social-btn:hover {
  background: #f7fafc;
  border-color: #cbd5e0;
}

.facebook-btn {
  color: #1877f2;
}

.google-btn {
  color: #2d3748;
}

/* Responsive */
@media (max-width: 640px) {
  .logo {
    top: 20px;
    left: 20px;
    font-size: 28px;
  }

  .title {
    font-size: 28px;
  }

  .form-content {
    max-width: 100%;
  }
}
</style>
