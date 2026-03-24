//frontend\src\services\cartService.js
import axios from "../plugins/axios";

const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

const cartService = {
  // Get user's cart
  async getCart() {
    try {
      const response = await axios.get(`${API_URL}/cart`, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
          Accept: "application/json",
        },
      });
      return response.data;
    } catch (error) {
      console.error("Error fetching cart:", error);
      throw error;
    }
  },

  // Add item to cart
  async addToCart(itemData) {
    try {
      const response = await axios.post(`${API_URL}/cart/add`, itemData, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
          "Content-Type": "application/json",
          Accept: "application/json",
        },
      });
      return response.data;
    } catch (error) {
      console.error("Error adding to cart:", error);

      // Handle specific error cases
      if (error.response?.status === 400) {
        throw new Error(
          error.response.data.message || "Cannot add item to cart",
        );
      }

      throw error;
    }
  },

  // Update cart item quantity
  async updateCartItem(itemId, quantity) {
    try {
      const response = await axios.put(
        `${API_URL}/cart/update/${itemId}`,
        {
          quantity: quantity,
        },
        {
          headers: {
            Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
            "Content-Type": "application/json",
            Accept: "application/json",
          },
        },
      );
      return response.data;
    } catch (error) {
      console.error("Error updating cart item:", error);

      if (error.response?.status === 400) {
        throw new Error(
          error.response.data.message || "Cannot update quantity",
        );
      }

      throw error;
    }
  },

  // Remove item from cart
  async removeFromCart(itemId) {
    try {
      const response = await axios.delete(`${API_URL}/cart/remove/${itemId}`, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
          Accept: "application/json",
        },
      });
      return response.data;
    } catch (error) {
      console.error("Error removing from cart:", error);
      throw error;
    }
  },

  // Clear cart
  async clearCart() {
    try {
      const response = await axios.delete(`${API_URL}/cart/clear`, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
          Accept: "application/json",
        },
      });
      return response.data;
    } catch (error) {
      console.error("Error clearing cart:", error);
      throw error;
    }
  },

  // Get cart summary
  async getCartSummary() {
    try {
      const response = await axios.get(`${API_URL}/cart/summary`, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
          Accept: "application/json",
        },
      });
      return response.data;
    } catch (error) {
      console.error("Error getting cart summary:", error);
      throw error;
    }
  },

  // Validate cart before checkout
  async validateCart() {
    try {
      const response = await axios.get(`${API_URL}/cart/validate`, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
          Accept: "application/json",
        },
      });
      return response.data;
    } catch (error) {
      console.error("Error validating cart:", error);
      throw error;
    }
  },
};

export default cartService;
