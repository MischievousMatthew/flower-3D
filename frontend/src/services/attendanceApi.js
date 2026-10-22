import api from "../plugins/axios";

// Time in / out goes through the shop scanner (services/attendanceKiosk.js), not here.
const attendanceApi = {
  /** Daily log with late / undertime / absent, plus per-person totals */
  async report(from, to, employeeId = null) {
    const { data } = await api.get("/attendance/report", { params: { from, to, ...(employeeId ? { employee_id: employeeId } : {}) } });
    return data.data;
  },

  /** Flagged scans, corrections waiting, and blocked attempts */
  async reviewQueue() {
    const { data } = await api.get("/attendance/review");
    return data.data;
  },

  async decide(recordId, decision, note) {
    const { data } = await api.post(`/attendance/${recordId}/review`, { decision, note });
    return data;
  },

  /** Approve overtime (optionally fewer minutes than worked) or reject it */
  async decideOvertime(recordId, decision, minutes, note) {
    const { data } = await api.post(`/attendance/${recordId}/overtime`, { decision, minutes, note });
    return data;
  },

  /** Ask for a day's times to be fixed or added; someone else approves it */
  async requestCorrection(body) {
    const { data } = await api.post("/attendance/corrections", body);
    return data;
  },

  async decideCorrection(id, decision, note) {
    const { data } = await api.post(`/attendance/corrections/${id}/review`, { decision, note });
    return data;
  },

  /** A short-lived link to an evidence photo */
  async evidence(recordId, which, index) {
    const { data } = await api.get(`/attendance/${recordId}/evidence/${which}/${index}`);
    return data.data.url;
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
