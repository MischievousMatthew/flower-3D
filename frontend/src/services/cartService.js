//frontend\src\services\cartService.js
import api from "../plugins/axios";

const cartService = {
  // Get user's cart
  async getCart() {
    try {
      const response = await api.get('/cart');
      return response.data;
    } catch (error) {
      console.error("Error fetching cart:", error);
      throw error;
    }
  },

  // Add item to cart
  async addToCart(itemData) {
    try {
      const response = await api.post('/cart/add', itemData);
      return response.data;
    } catch (error) {
      console.error("Error adding to cart:", error);
      if (error.response?.status === 400) {
        throw new Error(error.response.data.message || "Cannot add item to cart");
      }
      throw error;
    }
  },

  // Update cart item quantity
  async updateCartItem(itemId, quantity) {
    try {
      const response = await api.put(`/cart/update/${itemId}`, { quantity });
      return response.data;
    } catch (error) {
      console.error("Error updating cart item:", error);
      if (error.response?.status === 400) {
        throw new Error(error.response.data.message || "Cannot update quantity");
      }
      throw error;
    }
  },

  // Remove item from cart
  async removeFromCart(itemId) {
    try {
      const response = await api.delete(`/cart/remove/${itemId}`);
      return response.data;
    } catch (error) {
      console.error("Error removing from cart:", error);
      throw error;
    }
  },

  // Clear cart
  async clearCart() {
    try {
      const response = await api.delete('/cart/clear');
      return response.data;
    } catch (error) {
      console.error("Error clearing cart:", error);
      throw error;
    }
  },

  // Get cart summary
  async getCartSummary() {
    try {
      const response = await api.get('/cart/summary');
      return response.data;
    } catch (error) {
      console.error("Error getting cart summary:", error);
      throw error;
    }
  },

  // Validate cart before checkout
  async validateCart() {
    try {
      const response = await api.get('/cart/validate');
      return response.data;
    } catch (error) {
      console.error("Error validating cart:", error);
      throw error;
    }
  },
};

export default cartService;
