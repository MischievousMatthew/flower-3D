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
  recalculateTotals: (id) => api.post(`${BASE}/${id}/recalculate`),
  approvedFundingRequests: () => api.get(`${BASE}/funding-requests/approved`),
  remove: (id) => api.delete(`${BASE}/${id}`),
  // Draft → Pending approval → Ordered
  submit: (id) => api.post(`${BASE}/${id}/submit`),
  approve: (id, data = {}) => api.post(`${BASE}/${id}/approve`, data),
  sendBack: (id, reason) => api.post(`${BASE}/${id}/send-back`, { reason }),
  email: (id) => api.post(`${BASE}/${id}/email`),
  // What arrived: [{ id, qty }]
  receive: (id, items, note) => api.post(`${BASE}/${id}/receive`, { items, note }),
  stockItems: (search) => api.get(`${BASE}/stock-items`, { params: { search } }),
  createFromFunding: (fundingRequestId, supplierId) =>
    api.post(`${BASE}/from-funding/${fundingRequestId}`, {
      supplier_id: supplierId,
    }),
};
