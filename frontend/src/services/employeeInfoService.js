// src/services/employeeInfoService.js
import api from "../plugins/axios";

/**
 * Employee Info API Service
 * Handles all API calls for employee information management
 */
export const employeeInfoService = {
  /**
   * Get all employee info records with optional filters
   * @param {Object} params - Query parameters
   * @returns {Promise}
   */
  async getAll(params = {}) {
    try {
      const response = await api.get("/employees-info", { params });
      return response.data;
    } catch (error) {
      throw this.handleError(error);
    }
  },

  /**
   * Get a single employee info record by ID
   * @param {Number} id - Employee info ID
   * @returns {Promise}
   */
  async getById(id) {
    try {
      const response = await api.get(`/employees-info/${id}`);
      return response.data;
    } catch (error) {
      throw this.handleError(error);
    }
  },

  /**
   * Create a new employee info record
   * @param {Object} employeeData - Employee form data
   * @returns {Promise}
   */
  async create(employeeData) {
    try {
      // If employeeData is FormData (contains file upload), set proper headers
      const config =
        employeeData instanceof FormData
          ? { headers: { "Content-Type": "multipart/form-data" } }
          : {};
      const response = await api.post("/employees-info", employeeData, config);
      return response.data;
    } catch (error) {
      throw this.handleError(error);
    }
  },

  /**
   * Update an existing employee info record
   * @param {Number} id - Employee info ID
   * @param {Object} employeeData - Updated employee data
   * @returns {Promise}
   */
  async update(id, employeeData) {
    try {
      // If employeeData is FormData (contains file upload), use POST with _method override
      if (employeeData instanceof FormData) {
        employeeData.append("_method", "PUT");
        const response = await api.post(`/employees-info/${id}`, employeeData, {
          headers: {
            "Content-Type": "multipart/form-data",
          },
        });
        return response.data;
      } else {
        // Regular JSON update
        const response = await api.put(`/employees-info/${id}`, employeeData);
        return response.data;
      }
    } catch (error) {
      throw this.handleError(error);
    }
  },

  /**
   * Update only the employment status
   * @param {Number} id - Employee info ID
   * @param {String} status - New employment status
   * @returns {Promise}
   */
  async updateStatus(id, status) {
    try {
      const response = await api.patch(`/employees-info/${id}/status`, {
        employment_status: status,
      });
      return response.data;
    } catch (error) {
      throw this.handleError(error);
    }
  },

  /**
   * Delete an employee info record (soft delete)
   * @param {Number} id - Employee info ID
   * @returns {Promise}
   */
  async delete(id) {
    try {
      const response = await api.delete(`/employees-info/${id}`);
      return response.data;
    } catch (error) {
      throw this.handleError(error);
    }
  },

  /**
   * Get employee statistics
   * @returns {Promise}
   */
  async getStatistics() {
    try {
      const response = await api.get("/employees-info/statistics");
      return response.data;
    } catch (error) {
      throw this.handleError(error);
    }
  },

  /**
   * Handle API errors and format them for the frontend
   * @param {Error} error - Axios error object
   * @returns {Object} - Formatted error object
   */
  handleError(error) {
    if (error.response) {
      const { status, data } = error.response;

      if (status === 422) {
        // Validation errors
        return {
          message: data.message || "Validation failed",
          errors: data.errors || {},
          status: 422,
        };
      } else if (status === 404) {
        return {
          message: data.message || "Employee record not found",
          status: 404,
        };
      } else if (status === 401) {
        return {
          message: "Unauthorized. Please login again.",
          status: 401,
        };
      } else if (status === 403) {
        return {
          message: data.message || "Access forbidden",
          status: 403,
        };
      } else {
        return {
          message: data.message || "An error occurred",
          status: status,
        };
      }
    } else if (error.request) {
      return {
        message: "Network error. Please check your connection.",
        status: 0,
      };
    } else {
      return {
        message: error.message || "An unexpected error occurred",
        status: 0,
      };
    }
  },
};

export default employeeInfoService;
