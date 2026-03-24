// src/services/shipmentService.js
import api from "../plugins/axios";

export const shipmentService = {
  list: (params = {}) => api.get("/shipments", { params }),
  find: (id) => api.get(`/shipments/${id}`),
  create: (data) => api.post("/shipments", data),
  update: (id, data) => api.put(`/shipments/${id}`, data),
  updateTracking: (id, data) => api.patch(`/shipments/${id}/tracking`, data),
  markShipped: (id, data) => api.patch(`/shipments/${id}/ship`, data),
  markReceived: (id, data) => api.patch(`/shipments/${id}/receive`, data),
  updateStatus: (id, status) =>
    api.patch(`/shipments/${id}/status`, { status }),
};
