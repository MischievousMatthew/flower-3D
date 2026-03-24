import { ref } from "vue";
import api from "../plugins/axios";

export function useOtp() {
  const loading = ref(false);
  const error = ref(null);
  const cooldown = ref(0);
  const canResend = ref(true);

  let cooldownInterval = null;

  const sendOtp = async (contactNumber) => {
    try {
      loading.value = true;
      error.value = null;

      const response = await api.post("/auth/send-otp", {
        contact_number: contactNumber,
      });

      console.log("OTP Response:", response.data);

      startCooldown(60);

      return { success: true, data: response.data };
    } catch (err) {
      const message = err.response?.data?.message || "Failed to send OTP";
      error.value = message;
      return { success: false, error: message };
    } finally {
      loading.value = false;
    }
  };

  const startCooldown = (seconds) => {
    cooldown.value = seconds;
    canResend.value = false;

    if (cooldownInterval) {
      clearInterval(cooldownInterval);
    }

    cooldownInterval = setInterval(() => {
      cooldown.value--;

      if (cooldown.value <= 0) {
        clearInterval(cooldownInterval);
        canResend.value = true;
      }
    }, 1000);
  };

  const resetCooldown = () => {
    if (cooldownInterval) {
      clearInterval(cooldownInterval);
    }
    cooldown.value = 0;
    canResend.value = true;
  };

  return {
    loading,
    error,
    cooldown,
    canResend,
    sendOtp,
    resetCooldown,
  };
}
