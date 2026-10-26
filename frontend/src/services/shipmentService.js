// src/services/shipmentService.js
import api from "../plugins/axios";

// The server serves shipments under the supply chain routes
const BASE = "/procurement/supply-chain/shipments";

export const shipmentService = {
  list: (params = {}) => api.get(BASE, { params }),
  find: (id) => api.get(`${BASE}/${id}`),
  // { purchase_order_id, carrier, tracking_number?, items? }
  create: (data) => api.post(BASE, data),
  update: (id, data) => api.put(`${BASE}/${id}`, data),
  updateTracking: (id, data) => api.patch(`${BASE}/${id}/tracking`, data),
  markShipped: (id, data) => api.patch(`${BASE}/${id}/ship`, data),
  markReceived: (id, data) => api.patch(`${BASE}/${id}/receive`, data),
  updateStatus: (id, status) => api.patch(`${BASE}/${id}/status`, { status }),
};
