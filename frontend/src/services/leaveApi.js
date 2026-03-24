import api from "../plugins/axios";

const leaveApi = {
  /**
   * PUBLIC ENDPOINTS (No authentication required)
   */

  // Verify QR token and get employee details
  async verifyQRToken(token) {
    try {
      const response = await api.post("/public/leave/verify-qr", { token });
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Submit leave request (public)
  async submitLeaveRequest(data) {
    try {
      const response = await api.post("/public/leave/submit", data);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  /**
   * PROTECTED ENDPOINTS (Requires authentication)
   */

  // Get all leave requests (admin)
  async getLeaves(params = {}) {
    try {
      const response = await api.get("/leaves", { params });
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Get leave statistics
  async getStatistics() {
    try {
      const response = await api.get("/leaves/statistics");
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },

  // Approve or reject leave — reviewer is resolved automatically from the auth session
  async updateLeaveStatus(id, status, adminNotes = null) {
    try {
      const response = await api.put(`/leaves/${id}/status`, {
        status,
        admin_notes: adminNotes,
      });
      return response.data;
    } catch (error) {
      console.error(error.response?.data || error);
      throw error.response?.data || error;
    }
  },

  // Delete leave request
  async deleteLeave(id) {
    try {
      const response = await api.delete(`/leaves/${id}`);
      return response.data;
    } catch (error) {
      throw error.response?.data || error;
    }
  },
};

export default leaveApi;
