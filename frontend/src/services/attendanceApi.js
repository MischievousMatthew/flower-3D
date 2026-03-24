import api from "../plugins/axios";

const attendanceApi = {
  /**
   * Scan QR code for attendance
   */
  async scanQR(qrData) {
    try {
      const response = await api.post("/attendance/scan", {
        qr_data: qrData,
      });
      return response.data;
    } catch (error) {
      console.error("Scan QR Error:", error.response?.data || error.message);
      throw error;
    }
  },

  /**
   * Get attendance records
   * Automatically removes empty query params
   */
  async getRecords(params = {}) {
    try {
      // Remove empty, null, undefined values
      const cleanParams = Object.fromEntries(
        Object.entries(params).filter(
          ([_, value]) => value !== "" && value !== null && value !== undefined,
        ),
      );

      const response = await api.get("/attendance", {
        params: cleanParams,
      });

      console.log("Sending params:", cleanParams);

      return response.data;
    } catch (error) {
      console.error(
        "Get Records Error:",
        error.response?.data || error.message,
      );
      throw error;
    }
  },

  /**
   * Get attendance summary (date range)
   */
  async getSummary(startDate, endDate) {
    try {
      const response = await api.get("/attendance/summary", {
        params: {
          start_date: startDate,
          end_date: endDate,
        },
      });

      return response.data;
    } catch (error) {
      console.error(
        "Get Summary Error:",
        error.response?.data || error.message,
      );
      throw error;
    }
  },

  /**
   * Get today's summary
   */
  async getTodaySummary() {
    try {
      const today = new Date().toISOString().split("T")[0];

      const response = await api.get("/attendance/summary", {
        params: {
          start_date: today,
          end_date: today,
        },
      });

      return response.data;
    } catch (error) {
      console.error(
        "Today's Summary Error:",
        error.response?.data || error.message,
      );
      throw error;
    }
  },

  /**
   * Update attendance record
   */
  async update(id, data) {
    try {
      const response = await api.put(`/attendance/${id}`, data);
      return response.data;
    } catch (error) {
      console.error(
        "Update Attendance Error:",
        error.response?.data || error.message,
      );
      throw error;
    }
  },

  /**
   * Delete attendance record (optional but useful)
   */
  async delete(id) {
    try {
      const response = await api.delete(`/attendance/${id}`);
      return response.data;
    } catch (error) {
      console.error(
        "Delete Attendance Error:",
        error.response?.data || error.message,
      );
      throw error;
    }
  },
};

export default attendanceApi;
