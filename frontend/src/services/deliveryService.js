// src/services/deliveryService.js
import api from "../plugins/axios";

export const deliveryService = {
  // ── Universal barcode scan ────────────────────────────────────────────────
  // scanner_page: "to_process" | "to_ship" | "to_receive" | "completed"
  scan: (barcode, scannerPage) =>
    api.post("/sc/barcode/scan", {
      barcode: String(barcode),
      scanner_page: scannerPage,
    }),

  // ── Vendor: their own delivery list (VendorOrders.vue) ───────────────────
  vendorOrders: (params = {}) => api.get("/vendor/deliveries", { params }),

  // ── SC Coordinator: all deliveries across all vendors (SCOrders.vue) ─────
  scOrders: (params = {}) => api.get("/sc/orders", { params }),

  // ── Customer: polls delivery progress for their own order ────────────────
  customerOrder: (orderId) => api.get(`/customer/orders/${orderId}/delivery`),

  // ── Delivery management (SC override + audit) ─────────────────────────────
  updateStatus: (id, status) =>
    api.patch(`/deliveries/${id}/status`, { status }),

  getLogs: (id) => api.get(`/deliveries/${id}/logs`),

  // ── Barcode for a delivery order ──────────────────────────────────────────
  getBarcode: (orderId) => api.get(`/sc/orders/${orderId}/barcode`),

  // ── Return & Refund Requests (SC coordinator) ─────────────────────────────

  /** Fetch paginated return/refund requests for this vendor's orders */
  getOrderRequests: (params = {}) => api.get("/sc/order-requests", { params }),

  /** Approve a pending request — optionally include { admin_notes: "..." } */
  approveOrderRequest: (id, payload = {}) =>
    api.post(`/sc/order-requests/${id}/approve`, payload),

  /** Reject a pending request — optionally include { admin_notes: "..." } */
  rejectOrderRequest: (id, payload = {}) =>
    api.post(`/sc/order-requests/${id}/reject`, payload),
};
