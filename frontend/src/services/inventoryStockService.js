// src/services/inventoryStockService.js — stock & replenishment on the products' own stock count
import api from "../plugins/axios";

const BASE = "/procurement/inventory/stock";

export const inventoryStockService = {
  // Every item with on hand, minimum, target, on order, use and a reorder suggestion
  overview: () => api.get(BASE),
  // Recent changes: { filter: "damaged" } for damage only
  history: (params = {}) => api.get(`${BASE}/history`, { params }),
  setLevels: (id, data) => api.patch(`${BASE}/${id}/levels`, data),
  // mode: add | remove | set
  adjust: (id, data) => api.post(`${BASE}/${id}/adjust`, data),
  addMaterial: (data) => api.post(`${BASE}/materials`, data),
};
