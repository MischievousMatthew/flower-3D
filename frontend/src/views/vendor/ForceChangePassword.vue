<template>
  <div class="force-password-container">
    <!-- Logo -->
    <div class="logo">
      <img
        src="../../../public/bloomcraft-blankBg.png"
        alt="Bloomcraft Logo"
        width="60"
        height="60"
      />
    </div>

    <!-- Header -->
    <div class="header">
      <h1 class="title">Action Required</h1>
      <p class="subtitle">
        For your security, please change your auto-generated password before accessing your vendor portal.
      </p>
    </div>

    <!-- Form Content -->
    <div class="form-content">
      <form @submit.prevent="changePassword" class="password-form">
        <!-- Success Alert -->
        <div v-if="successMessage" class="alert-success">
          {{ successMessage }}
        </div>
        <!-- Error Alert -->
        <div v-if="errorMessage" class="alert-error">
          {{ errorMessage }}
        </div>

        <div class="input-group">
          <label class="input-label">Current Password *</label>
          <div class="password-field">
            <input
              v-model="passwordData.current_password"
              :type="showPasswords.current ? 'text' : 'password'"
              required
              placeholder="Enter your current password"
              class="form-input"
              :class="{ 'input-error': errors.current_password }"
            />
            <button
              type="button"
              @click="showPasswords.current = !showPasswords.current"
              class="toggle-password"
              tabindex="-1"
            >
              {{ showPasswords.current ? "😌" : "🫣" }}
            </button>
          </div>
          <span v-if="errors.current_password" class="error-msg">{{
            errors.current_password[0] || errors.current_password
          }}</span>
        </div>

        <div class="input-group">
          <label class="input-label">New Password *</label>
          <div class="password-field">
            <input
              v-model="passwordData.password"
              :type="showPasswords.new ? 'text' : 'password'"
              required
              placeholder="Minimum 8 characters"
              class="form-input"
              :class="{ 'input-error': errors.password }"
            />
            <button
              type="button"
              @click="showPasswords.new = !showPasswords.new"
              class="toggle-password"
              tabindex="-1"
            >
              {{ showPasswords.new ? "😌" : "🫣" }}
            </button>
          </div>
          <span v-if="errors.password" class="error-msg">{{
            errors.password[0] || errors.password
          }}</span>
        </div>

        <div class="input-group">
          <label class="input-label">Confirm New Password *</label>
          <div class="password-field">
            <input
              v-model="passwordData.password_confirmation"
              :type="showPasswords.confirm ? 'text' : 'password'"
              required
              placeholder="Repeat new password"
              class="form-input"
              :class="{ 'input-error': errors.password_confirmation }"
            />
            <button
              type="button"
              @click="showPasswords.confirm = !showPasswords.confirm"
              class="toggle-password"
              tabindex="-1"
            >
              {{ showPasswords.confirm ? "😌" : "🫣" }}
            </button>
          </div>
          <span v-if="errors.password_confirmation" class="error-msg">{{
            errors.password_confirmation[0] || errors.password_confirmation
          }}</span>
        </div>

        <!-- Submit Button -->
        <button type="submit" :disabled="loading" class="submit-btn">
          <span v-if="loading">Updating Password...</span>
          <span v-else>Update Password</span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from "vue";
import { useRouter } from "vue-router";
import api from "../../plugins/axios";
import { useAuth } from "../../composables/useAuth";

const router = useRouter();
const auth = useAuth();
const loading = ref(false);

const passwordData = reactive({
  current_password: "",
  password: "",
  password_confirmation: "",
});

const showPasswords = reactive({
  current: false,
  new: false,
  confirm: false,
});

const errors = ref({});
const errorMessage = ref("");
const successMessage = ref("");

const changePassword = async () => {
  errors.value = {};
  errorMessage.value = "";
  successMessage.value = "";

  if (passwordData.password !== passwordData.password_confirmation) {
    errors.value = { password_confirmation: ["Passwords do not match"] };
    return;
  }

  loading.value = true;
  try {
    const response = await api.put("/vendor/profile/change-password", {
      current_password: passwordData.current_password,
      password: passwordData.password,
      password_confirmation: passwordData.password_confirmation,
    });

    if (response.data.success) {
      successMessage.value = "Password updated successfully! Redirecting...";
      
      // Reload user data to clear the needs_password_change flag in frontend
      await auth.loadUser();
      
      // Redirect to vendor dashboard
      setTimeout(() => {
        router.push("/vendor/products");
      }, 1500);
    }
  } catch (error) {
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors || {};
    } else {
      errorMessage.value =
        error.response?.data?.message || "Failed to update password. Please try again.";
    }
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.force-password-container {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: #f7fafc;
  padding: 40px 20px;
  position: relative;
}

.logo {
  margin-bottom: 24px;
}

.header {
  text-align: center;
  margin-bottom: 32px;
  max-width: 480px;
}

.title {
  font-size: 28px;
  font-weight: 600;
  color: #2d3748;
  margin-bottom: 12px;
}

.subtitle {
  font-size: 15px;
  color: #4a5568;
  font-weight: 400;
  line-height: 1.5;
}

.form-content {
  width: 100%;
  max-width: 400px;
  background: white;
  padding: 32px;
  border-radius: 12px;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
}

.password-form {
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

.alert-success {
  padding: 12px 16px;
  background: #d1fae5;
  color: #059669;
  border: 1px solid #6ee7b7;
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
  font-weight: 500;
  color: #4a5568;
  margin-bottom: 8px;
}

.form-input {
  width: 100%; /* Use 100% since it's already properly padded */
  box-sizing: border-box;
  padding: 12px 40px 12px 16px; /* Space for the eye icon */
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
  box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15);
}

.form-input::placeholder {
  color: #a0aec0;
}

.password-field {
  position: relative;
  display: flex;
  align-items: center;
}

.toggle-password {
  position: absolute;
  right: 12px;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 18px;
  opacity: 0.5;
  transition: opacity 0.2s;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
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

.submit-btn {
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
}

.submit-btn:hover:not(:disabled) {
  background: #1a202c;
}

.submit-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
