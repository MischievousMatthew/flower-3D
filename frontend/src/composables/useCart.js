import { ref, computed } from "vue";

const cart = ref([]);
const cartCount = ref(0);

export function useCart() {
  const addToCart = (product, quantity = 1) => {
    const existingItem = cart.value.find((item) => item.id === product.id);

    if (existingItem) {
      existingItem.quantity += quantity;
    } else {
      cart.value.push({
        ...product,
        quantity,
        addedAt: new Date(),
      });
    }

    cartCount.value = cart.value.reduce(
      (total, item) => total + item.quantity,
      0,
    );

    localStorage.setItem("cart", JSON.stringify(cart.value));
  };

  const removeFromCart = (productId) => {
    const index = cart.value.findIndex((item) => item.id === productId);
    if (index !== -1) {
      cartCount.value -= cart.value[index].quantity;
      cart.value.splice(index, 1);
      localStorage.setItem("cart", JSON.stringify(cart.value));
    }
  };

  const clearCart = () => {
    cart.value = [];
    cartCount.value = 0;
    localStorage.removeItem("cart");
  };

  const cartTotal = computed(() => {
    return cart.value.reduce(
      (total, item) => total + item.selling_price * item.quantity,
      0,
    );
  });

  const loadCart = () => {
    const savedCart = localStorage.getItem("cart");
    if (savedCart) {
      cart.value = JSON.parse(savedCart);
      cartCount.value = cart.value.reduce(
        (total, item) => total + item.quantity,
        0,
      );
    }
  };

  return {
    cart,
    cartCount,
    cartTotal,
    addToCart,
    removeFromCart,
    clearCart,
    loadCart,
  };
}
