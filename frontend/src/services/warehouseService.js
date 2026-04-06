// src/services/warehouseService.js
import api from "../plugins/axios";

const WAREHOUSE_INVENTORY_EVENT = "warehouse:inventory-changed";

const BASE = "/procurement/supply-chain/warehouses";
const PRODUCTS = "/procurement/inventory/products/search"; // ← ERP warehouse search (scoped by employee owner_id)

export const warehouseService = {
  // ── Warehouses ────────────────────────────────────────────────────────────
  list: (params = {}) => api.get(BASE, { params }),
  find: (id) => api.get(`${BASE}/${id}`),
  create: (data) => api.post(BASE, data),
  update: (id, data) => api.put(`${BASE}/${id}`, data),
  destroy: (id) => api.delete(`${BASE}/${id}`),

  // ── Product catalog search (used in AddItem.vue search dropdown) ──────────
  // Searches ALL vendor products — not scoped to a warehouse.
  // GET /procurement/inventory/products?search=...&per_page=...
  searchProducts: (params = {}) => api.get(PRODUCTS, { params }),

  // ── Inventory view (products already stored in a specific warehouse) ──────
  // GET /procurement/supply-chain/warehouses/:warehouseId/items
  // Returns paginated Product list WITH warehouse_units_stored & warehouse_batch_count.
  // Used by WarehouseInventory.vue to display current stock.
  catalogProducts: (warehouseId, params = {}) =>
    api.get(`${BASE}/${warehouseId}/items`, { params }),

  // ── Add Item = pick a catalog product → creates a WarehouseBatch ─────────
  // POST /procurement/supply-chain/warehouses/:warehouseId/items
  addItem: (warehouseId, data) =>
    api.post(`${BASE}/${warehouseId}/items`, data),

  // ── Legacy item helpers ───────────────────────────────────────────────────
  updateItem: (warehouseId, id, data) =>
    api.put(`${BASE}/${warehouseId}/items/${id}`, data),
  adjustStock: (warehouseId, id, data) =>
    api.patch(`${BASE}/${warehouseId}/items/${id}/stock`, data),

  // ── Movements & Barcodes ──────────────────────────────────────────────────
  movements: (warehouseId, params = {}) =>
    api.get(`${BASE}/${warehouseId}/movements`, { params }),
  barcodes: (warehouseId) => api.get(`${BASE}/${warehouseId}/barcodes`),
};

export function notifyWarehouseInventoryChanged(detail = {}) {
  if (typeof window === "undefined") return;

  window.dispatchEvent(
    new CustomEvent(WAREHOUSE_INVENTORY_EVENT, {
      detail: { ...detail, timestamp: Date.now() },
    }),
  );
}

export function onWarehouseInventoryChanged(handler) {
  if (typeof window === "undefined") return () => {};

  window.addEventListener(WAREHOUSE_INVENTORY_EVENT, handler);

  return () => window.removeEventListener(WAREHOUSE_INVENTORY_EVENT, handler);
}
