import api from "../plugins/axios";

const productService = {
  // Get all approved products (for customers) - PUBLIC
  async getAllProducts(filters = {}) {
    try {
      const response = await api.get('/customer/products', {
        params: filters,
      });
      return response.data;
    } catch (error) {
      console.error("Error fetching products:", error);
      throw error;
    }
  },

  // Get filter options - PUBLIC
  async getFilterOptions() {
    try {
      const response = await api.get('/customer/products/filters');
      return response.data;
    } catch (error) {
      console.error("Error fetching filter options:", error);
      throw error;
    }
  },

  // Get product by ID - PUBLIC
  async getProductById(id) {
    try {
      const response = await api.get(`/customer/products/${id}`);
      return response.data;
    } catch (error) {
      console.error("Error fetching product:", error);
      throw error;
    }
  },

  // Get featured products - PUBLIC
  async getFeaturedProducts() {
    try {
      const response = await api.get('/customer/products/featured');
      return response.data;
    } catch (error) {
      console.error("Error fetching featured products:", error);
      throw error;
    }
  },

  // Get new arrivals - PUBLIC
  async getNewArrivals() {
    try {
      const response = await api.get('/customer/products/new-arrivals');
      return response.data;
    } catch (error) {
      console.error("Error fetching new arrivals:", error);
      throw error;
    }
  },

  // Search products - PUBLIC
  async searchProducts(query) {
    try {
      const response = await api.get('/customer/products/search', {
        params: { query },
      });
      return response.data;
    } catch (error) {
      console.error("Error searching products:", error);
      throw error;
    }
  },

  // Get all categories - PUBLIC
  async getCategories() {
    try {
      const response = await api.get('/customer/products/categories');
      return response.data;
    } catch (error) {
      console.error("Error fetching categories:", error);
      throw error;
    }
  },
};

export default productService;
