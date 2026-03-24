// src/services/scanService.js
import api from "../plugins/axios";

/**
 * Core barcode scan — single endpoint for all scan workflows.
 * The API resolves context from the barcode prefix/format and returns
 * a typed response: { type: 'item' | 'order' | 'shipment', data: {...} }
 */
export const scanService = {
  // ── POST /barcode/scan ─────────────────────────────────────────────────────
  scan(barcode) {
    return api.post("/barcode/scan", { barcode: String(barcode) });
  },

  // ── POST /barcode/generate ─────────────────────────────────────────────────
  // Generates barcode for a warehouse item (called on item creation)
  generateItemBarcode(itemId) {
    return api.post("/barcode/generate", { item_id: itemId });
  },

  // ── GET /barcode/lookup ────────────────────────────────────────────────────
  lookup(barcode) {
    return api.get("/barcode/lookup", { params: { barcode } });
  },

  // ── PATCH /barcode/stock ───────────────────────────────────────────────────
  updateStockFromScan(barcode, quantity, direction, reference = "") {
    return api.patch("/barcode/stock", {
      barcode,
      quantity,
      direction,
      reference,
    });
  },

  // ── Workflow-specific helpers ──────────────────────────────────────────────

  /**
   * TO PROCESS: scan an item barcode → attach item to a pending order.
   * Internally: scan → resolve item → POST /orders/{orderId}/items
   */
  async scanItemToOrder(barcode, orderId) {
    const scanRes = await this.scan(barcode);
    const itemData = scanRes.data;

    if (itemData.type !== "item") {
      throw new Error(`Expected item barcode, got: ${itemData.type}`);
    }

    const item = itemData.data;
    const orderRes = await api.post(`/orders/${orderId}/items`, {
      items: [
        {
          product_name: item.product_name,
          sku: item.sku,
          quantity: 1,
          price: 0,
        },
      ],
    });

    return { item, order: orderRes.data || orderRes };
  },

  /**
   * TO SHIP: scan an order barcode → PATCH /orders/{id}/status → shipped.
   * Internally: scan → resolve order → advance status
   */
  async scanOrderToShip(barcode) {
    const scanRes = await this.scan(barcode);
    const orderData = scanRes.data || scanRes;

    if (orderData.type !== "order") {
      throw new Error(`Expected order barcode, got: ${orderData.type}`);
    }

    const order = orderData.data;
    const statusRes = await api.patch(`/orders/${order.id}/status`, {
      status: "shipped",
    });

    return {
      order: { ...order, status: "shipped" },
      updated: statusRes.data || statusRes,
    };
  },

  /**
   * TO RECEIVE: scan a shipment barcode → PATCH /shipments/{id}/receive.
   * Internally: scan → resolve shipment → mark received → update stock
   */
  async scanShipmentToReceive(barcode) {
    const scanRes = await this.scan(barcode);
    const shipmentData = scanRes.data || scanRes;

    if (shipmentData.type !== "shipment") {
      throw new Error(`Expected shipment barcode, got: ${shipmentData.type}`);
    }

    const shipment = shipmentData.data;
    const receiveRes = await api.patch(`/shipments/${shipment.id}/receive`, {
      received_date: new Date().toISOString().slice(0, 10),
    });

    return {
      shipment: { ...shipment, status: "delivered" },
      updated: receiveRes.data || receiveRes,
    };
  },

  // ── Completed / History ────────────────────────────────────────────────────
  completedOrders(params = {}) {
    return api.get("/orders", {
      params: { ...params, status: "completed", per_page: 30 },
    });
  },
  completedShipments(params = {}) {
    return api.get("/shipments", {
      params: { ...params, status: "delivered", per_page: 30 },
    });
  },

  // ── Pending queues ─────────────────────────────────────────────────────────
  toProcessOrders() {
    return api.get("/orders", { params: { status: "pending", per_page: 50 } });
  },
  toShipOrders() {
    return api.get("/orders", {
      params: { status: "processing", per_page: 50 },
    });
  },
  toReceiveShipments() {
    return api.get("/shipments", {
      params: { status: "in_transit", per_page: 50 },
    });
  },
};
