//frontend\src\services\checkoutService.js

import api from "../plugins/axios";

const API_URL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";

const checkoutService = {
  /**
   * Initialize checkout session
   * @param {Object} data - { cart_item_ids: [], delivery_type: 'standard' }
   */
  async initializeCheckout(data) {
    try {
      const response = await api.post(`${API_URL}/checkout/initialize`, data, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
          "Content-Type": "application/json",
          Accept: "application/json",
        },
      });
      return response.data;
    } catch (error) {
      console.error("Error initializing checkout:", error);

      // Handle specific error cases
      if (error.response?.status === 400) {
        return {
          success: false,
          message:
            error.response.data.message || "Failed to initialize checkout",
          requires_address: error.response.data.requires_address || false,
        };
      }

      throw error;
    }
  },

  /**
   * Create order and process payment
   * @param {Object} data - Order data with payment details
   */
  async createOrder(data) {
    try {
      const response = await api.post(
        `${API_URL}/checkout/create-order`,
        data,
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
      console.error("Error creating order:", error);

      if (error.response?.status === 400 || error.response?.status === 500) {
        return {
          success: false,
          message: error.response.data.message || "Failed to create order",
        };
      }

      throw error;
    }
  },

  /**
   * Get order details
   * @param {Number} orderId - Order ID
   */
  async getOrder(orderId) {
    try {
      const response = await api.get(`${API_URL}/checkout/orders/${orderId}`, {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
          Accept: "application/json",
        },
      });
      return response.data;
    } catch (error) {
      console.error("Error fetching order:", error);

      if (error.response?.status === 404) {
        return {
          success: false,
          message: "Order not found",
        };
      }

      throw error;
    }
  },
};

export default checkoutService;
