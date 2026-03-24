import api from "../plugins/axios";

const payrollApi = {
  // ─── General ──────────────────────────────────────────────────────────────

  /**
   * Get payroll list (used by HR PayrollList)
   */
  async getPayrolls(params = {}) {
    try {
      const response = await api.get("/payroll", { params });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Preview payroll calculation
   */
  async previewPayroll(data) {
    try {
      const response = await api.post("/payroll/preview", data);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Generate payroll
   */
  async generatePayroll(data) {
    try {
      const response = await api.post("/payroll", data);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Get payroll summary
   */
  async getSummary(params = {}) {
    try {
      const response = await api.get("/payroll/summary", { params });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Get single payroll
   */
  async getPayroll(id) {
    try {
      const response = await api.get(`/payroll/${id}`);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Delete payroll (pending only)
   */
  async deletePayroll(id) {
    try {
      const response = await api.delete(`/payroll/${id}`);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  // ─── Finance Actions ───────────────────────────────────────────────────────

  /**
   * Get Finance inbox — returns pending payrolls for Finance review.
   * Hits the dedicated /payroll/finance-requests endpoint so Finance
   * can see ALL employees' payrolls (not just their own).
   * @param {object} params - { search, month, year, per_page, page }
   */
  async getFinanceRequests(params = {}) {
    try {
      const response = await api.get("/payroll/finance-requests", { params });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Get Finance summary counts (used by summary cards)
   */
  async getFinanceSummary(params = {}) {
    try {
      const response = await api.get("/payroll/finance-summary", { params });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Finance approves payroll requests → status becomes "approved"
   * HR can then select and mark these as paid.
   * @param {number[]} ids
   */
  async approvePayroll(ids) {
    try {
      const response = await api.post("/payroll/finance-approve", { ids });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  /**
   * Finance rejects payroll requests → status becomes "rejected"
   * @param {number[]} ids
   * @param {string}   notes - required rejection reason
   */
  async rejectPayroll(ids, notes = "") {
    try {
      const response = await api.post("/payroll/finance-reject", {
        ids,
        finance_notes: notes,
      });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  // ─── HR Actions ───────────────────────────────────────────────────────────

  /**
   * HR marks Finance-approved payrolls as paid → status becomes "paid"
   * @param {number[]} ids
   */
  async markAsPaid(ids) {
    try {
      const response = await api.post("/payroll/mark-paid", { ids });
      return response.data;
    } catch (error) {
      throw error;
    }
  },
};

export default payrollApi;
