// src/services/supplierService.js
import api from "../plugins/axios";

const BASE = "/procurement/supply-chain/suppliers";

export const supplierService = {
  list: (params = {}) => api.get(BASE, { params }),
  find: (id) => api.get(`${BASE}/${id}`),
  update: (id, data) => {
    if (data instanceof FormData) {
      data.append("_method", "PUT");
      return api.post(`${BASE}/${id}`, data, {
        headers: { "Content-Type": "multipart/form-data" },
      });
    }
    return api.put(`${BASE}/${id}`, data);
  },
  create: (data) =>
    api.post(BASE, data, {
      headers:
        data instanceof FormData
          ? { "Content-Type": "multipart/form-data" }
          : {},
    }),
  remove: (id) => api.delete(`${BASE}/${id}`),
  activate: (id) => api.patch(`${BASE}/${id}/activate`),
  deactivate: (id) => api.patch(`${BASE}/${id}/deactivate`),
  blacklist: (id) => api.patch(`${BASE}/${id}/blacklist`),

  // Contacts (nested under supplier)
  listContacts: (supplierId) => api.get(`${BASE}/${supplierId}/contacts`),
  addContact: (supplierId, data) =>
    api.post(`${BASE}/${supplierId}/contacts`, data),
  updateContact: (supplierId, id, data) =>
    api.put(`${BASE}/${supplierId}/contacts/${id}`, data),
  removeContact: (supplierId, id) =>
    api.delete(`${BASE}/${supplierId}/contacts/${id}`),
};
