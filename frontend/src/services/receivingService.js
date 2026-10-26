// src/services/receivingService.js — receiving & quality inspection
import api from "../plugins/axios";

const BASE = "/procurement/supply-chain/receiving";

export const receivingService = {
  // Approved orders with what's still owed on each line
  openOrders: () => api.get(`${BASE}/open-orders`),
  // Recent deliveries: { issues: 1 } for the ones with rejected or missing stems
  list: (params = {}) => api.get(BASE, { params }),
  find: (id) => api.get(`${BASE}/${id}`),
  // FormData: purchase_order_id, received_date, delivery_ref, notes, lines (JSON), receipt, photos[]
  inspect: (formData) => api.post(BASE, formData, { headers: { "Content-Type": "multipart/form-data" } }),
};
