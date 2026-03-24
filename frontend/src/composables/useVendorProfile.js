/**
 * useVendorProfile.js
 *
 * Composable for managing vendor profile data across the application
 * Automatically fetches vendor profile on mount and provides reactive data
 *
 * Usage:
 * import { useVendorProfile } from '@/composables/useVendorProfile';
 *
 * const { vendorProfile, isLoading, error, fetchProfile, refreshProfile } = useVendorProfile();
 */

import { ref, onMounted } from "vue";
import api from "@/plugins/axios";
import { toast } from "vue3-toastify";

// Shared state across all instances (singleton pattern)
const sharedVendorProfile = ref({
  id: null,
  store_name: "",
  store_description: "",
  store_address: "",
  service_areas: "",
  operating_hours: "",
  owner_name: "",
  position: "",
  contact_number: "",
  email: "",
  store_logo_url: null,
  profile_photo_url: null, // Same as store_logo_url
  facebook_page: "",
  instagram_page: "",

  // Payment details
  payout_method: "",
  account_holder_name: "",
  bank_name: "",
  billing_address: "",

  // Product details
  product_types: [],
  price_min: null,
  price_max: null,
  formatted_price_range: "",
  same_day_delivery: null,
  cutoff_times: [],

  // Delivery details
  delivery_handled_by: "self",
  max_orders_per_day: null,
  lead_time: "",
  cancellation_policy: "",

  // Completion status
  payment_details_completed: false,
  product_details_completed: false,
  delivery_details_completed: false,
  profile_fully_completed: false,
  profile_completion_percentage: 0,
});

const isLoading = ref(false);
const error = ref(null);
const hasLoaded = ref(false);

export function useVendorProfile(options = {}) {
  const {
    autoFetch = true,
    showToast = true,
    onSuccess = null,
    onError = null,
  } = options;

  /**
   * Fetch vendor profile from API
   */
  const fetchProfile = async () => {
    // If already loaded and not forcing refresh, skip
    if (hasLoaded.value && !options.forceRefresh) {
      return sharedVendorProfile.value;
    }

    isLoading.value = true;
    error.value = null;

    try {
      const response = await api.get("/vendor/profile");

      if (response.data.success) {
        // Update shared state
        Object.assign(sharedVendorProfile.value, response.data.data);
        hasLoaded.value = true;

        // Success callback
        if (onSuccess) {
          onSuccess(sharedVendorProfile.value);
        }

        return sharedVendorProfile.value;
      } else {
        throw new Error(response.data.message || "Failed to load profile");
      }
    } catch (err) {
      error.value = err.message || "An error occurred";

      if (showToast) {
        toast.error("Failed to load vendor profile");
      }

      if (onError) {
        onError(err);
      }

      throw err;
    } finally {
      isLoading.value = false;
    }
  };

  /**
   * Force refresh profile data
   */
  const refreshProfile = async () => {
    hasLoaded.value = false;
    return await fetchProfile();
  };

  /**
   * Update local profile data (optimistic update)
   */
  const updateLocalProfile = (updates) => {
    Object.assign(sharedVendorProfile.value, updates);
  };

  /**
   * Check if profile is complete
   */
  const isProfileComplete = () => {
    return sharedVendorProfile.value.profile_fully_completed;
  };

  /**
   * Get profile completion percentage
   */
  const getCompletionPercentage = () => {
    return sharedVendorProfile.value.profile_completion_percentage || 0;
  };

  /**
   * Check if specific section is complete
   */
  const isSectionComplete = (section) => {
    const sectionMap = {
      payment: "payment_details_completed",
      product: "product_details_completed",
      delivery: "delivery_details_completed",
    };

    const field = sectionMap[section];
    return field ? sharedVendorProfile.value[field] : false;
  };

  /**
   * Get store initials for avatar placeholder
   */
  const getStoreInitials = () => {
    const name = sharedVendorProfile.value.store_name || "S";
    const words = name.split(" ");
    return words.length > 1
      ? (words[0][0] + words[1][0]).toUpperCase()
      : words[0][0].toUpperCase();
  };

  // Auto-fetch on mount if enabled
  if (autoFetch) {
    onMounted(() => {
      if (!hasLoaded.value) {
        fetchProfile();
      }
    });
  }

  return {
    // State
    vendorProfile: sharedVendorProfile,
    isLoading,
    error,
    hasLoaded,

    // Methods
    fetchProfile,
    refreshProfile,
    updateLocalProfile,
    isProfileComplete,
    getCompletionPercentage,
    isSectionComplete,
    getStoreInitials,
  };
}

/**
 * Export for direct access to shared profile without reactivity
 */
export function getVendorProfile() {
  return sharedVendorProfile.value;
}

/**
 * Clear vendor profile (useful for logout)
 */
export function clearVendorProfile() {
  Object.keys(sharedVendorProfile.value).forEach((key) => {
    if (Array.isArray(sharedVendorProfile.value[key])) {
      sharedVendorProfile.value[key] = [];
    } else if (typeof sharedVendorProfile.value[key] === "boolean") {
      sharedVendorProfile.value[key] = false;
    } else if (typeof sharedVendorProfile.value[key] === "number") {
      sharedVendorProfile.value[key] = null;
    } else {
      sharedVendorProfile.value[key] = "";
    }
  });
  hasLoaded.value = false;
}
