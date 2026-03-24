// src/services/analyticsService.js
import api from "../plugins/axios";

const BASE = "/procurement/supply-chain/analytics";

export const analyticsService = {
  summary: (params = {}) => api.get(`${BASE}/summary`, { params }),
  inventory: (params = {}) => api.get(`${BASE}/inventory`, { params }),
  orders: (params = {}) => api.get(`${BASE}/orders`, { params }),
  shipments: (params = {}) => api.get(`${BASE}/shipments`, { params }),
  supplierPerformance: (params = {}) =>
    api.get(`${BASE}/supplier-performance`, { params }),
  movements: (params = {}) => api.get(`${BASE}/movements`, { params }),
};
