import api from "../plugins/axios";

const BASE = "/procurement/supply-chain/orders";

export const orderService = {
  list: (params = {}) => api.get(BASE, { params }),
  find: (id) => api.get(`${BASE}/${id}`),
  create: (data) => api.post(BASE, data),
  update: (id, data) => api.put(`${BASE}/${id}`, data),
  updateStatus: (id, status) => api.patch(`${BASE}/${id}/status`, { status }),
  attachItems: (id, items) => api.post(`${BASE}/${id}/items`, { items }),
  removeItem: (id, itemId) => api.delete(`${BASE}/${id}/items/${itemId}`),
  recalculateTotals: (id) => api.get(`${BASE}/${id}/recalculate`),
  approvedFundingRequests: () => api.get(`${BASE}/funding-requests/approved`),
  createFromFunding: (fundingRequestId, supplierId) =>
    api.post(`${BASE}/from-funding/${fundingRequestId}`, {
      supplier_id: supplierId,
    }),
};
