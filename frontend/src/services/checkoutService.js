//frontend\src\services\checkoutService.js

import api from "../plugins/axios";

const checkoutService = {
  async initializeCheckout(data) {
    try {
      const response = await api.post('/checkout/initialize', data);
      return response.data;
    } catch (error) {
      console.error("Error initializing checkout:", error);
      if (error.response?.status === 400) {
        return {
          success: false,
          message: error.response.data.message || "Failed to initialize checkout",
          requires_address: error.response.data.requires_address || false,
        };
      }
      throw error;
    }
  },

  async createOrder(data) {
    try {
      const response = await api.post('/checkout/create-order', data);
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

  async getOrder(orderId) {
    try {
      const response = await api.get(`/checkout/orders/${orderId}`);
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
