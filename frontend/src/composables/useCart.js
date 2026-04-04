import { computed, ref, watch } from "vue";
import cartService from "../services/cartService.js";

const STORAGE_KEY_PREFIX = "cart_snapshot";

const items = ref([]);
const isLoading = ref(false);
const isInitialized = ref(false);

let initializePromise = null;
let persistenceBound = false;

const getStorageKey = () => {
  try {
    const rawUser = localStorage.getItem("user");
    const user = rawUser ? JSON.parse(rawUser) : null;
    return `${STORAGE_KEY_PREFIX}:${user?.id ?? "guest"}`;
  } catch {
    return `${STORAGE_KEY_PREFIX}:guest`;
  }
};

const cloneItems = (cartItems = []) =>
  cartItems.map((item) => ({
    ...item,
    product: item.product
      ? {
          ...item.product,
          images: Array.isArray(item.product.images) ? item.product.images : [],
        }
      : null,
  }));

const persistItems = (cartItems) => {
  if (typeof window === "undefined") return;
  localStorage.setItem(getStorageKey(), JSON.stringify(cloneItems(cartItems)));
};

const restoreSnapshot = () => {
  if (typeof window === "undefined") return [];

  try {
    const snapshot = localStorage.getItem(getStorageKey());
    if (!snapshot) return [];
    const parsed = JSON.parse(snapshot);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
};

const syncItems = (cartItems = []) => {
  items.value = cloneItems(cartItems);
};

const count = computed(() =>
  items.value.reduce((total, item) => total + Number(item.quantity || 0), 0),
);

const uniqueCount = computed(() => items.value.length);

const bindPersistence = () => {
  if (persistenceBound || typeof window === "undefined") return;

  watch(
    items,
    (value) => {
      persistItems(value);
    },
    { deep: true },
  );

  window.addEventListener("storage", (event) => {
    if (event.key === getStorageKey()) {
      syncItems(restoreSnapshot());
    }
  });

  persistenceBound = true;
};

const refreshCart = async () => {
  if (typeof window === "undefined") return items.value;

  const token =
    localStorage.getItem("auth_token") ||
    localStorage.getItem("token") ||
    localStorage.getItem("employee_token") ||
    localStorage.getItem("vendor_token");

  if (!token) {
    syncItems(restoreSnapshot());
    isInitialized.value = true;
    return items.value;
  }

  isLoading.value = true;

  try {
    const response = await cartService.getCart();
    if (response?.success) {
      syncItems(response.data?.items || []);
      return items.value;
    }
  } catch (error) {
    console.error("Error refreshing cart:", error);
    syncItems(restoreSnapshot());
  } finally {
    isLoading.value = false;
    isInitialized.value = true;
  }

  return items.value;
};

const initialize = async ({ force = false } = {}) => {
  bindPersistence();

  if (!force && isInitialized.value) return items.value;
  if (!force && initializePromise) return initializePromise;

  syncItems(restoreSnapshot());

  initializePromise = refreshCart().finally(() => {
    initializePromise = null;
  });

  return initializePromise;
};

const addToCart = async (payload) => {
  const response = await cartService.addToCart(payload);
  if (response?.success) {
    syncItems(response.data?.items || response.data?.cart?.items || items.value);
    if (!response.data?.items && !response.data?.cart?.items) {
      await refreshCart();
    }
  }
  return response;
};

const updateCartItem = async (itemId, quantity) => {
  const response = await cartService.updateCartItem(itemId, quantity);
  if (response?.success) {
    syncItems(response.data?.items || response.data?.cart?.items || items.value);
    if (!response.data?.items && !response.data?.cart?.items) {
      await refreshCart();
    }
  }
  return response;
};

const removeFromCart = async (itemId) => {
  const response = await cartService.removeFromCart(itemId);
  if (response?.success) {
    syncItems(response.data?.items || response.data?.cart?.items || items.value);
    if (!response.data?.items && !response.data?.cart?.items) {
      await refreshCart();
    }
  }
  return response;
};

const clearCart = async () => {
  const response = await cartService.clearCart();
  if (response?.success) {
    syncItems([]);
  }
  return response;
};

const resetCart = () => {
  syncItems([]);
  isInitialized.value = false;
};

export function useCart() {
  return {
    items,
    count,
    uniqueCount,
    isLoading,
    isInitialized,
    initialize,
    refreshCart,
    addToCart,
    updateCartItem,
    removeFromCart,
    clearCart,
    resetCart,
  };
}
