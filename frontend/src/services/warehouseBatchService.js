// src/services/warehouseBatchService.js
import api from "../plugins/axios";

const BASE = "/procurement/supply-chain/warehouse/batches";
const LOC = "/procurement/supply-chain/warehouse/locations"; // ← FIXED: was "/warehouse/locations"

// ── Batch API ────────────────────────────────────────────────────────────────

export const warehouseBatchService = {
  // Read
  floorView: (params = {}) => api.get(`${BASE}/floor-view`, { params }),
  byProduct: (productId) => api.get(`${BASE}/product/${productId}`),
  logs: (batchId) => api.get(`${BASE}/${batchId}/logs`),

  // Scanner
  scan: (barcode) => api.post(`${BASE}/scan`, { barcode }),

  // Write
  receive: (data) => api.post(BASE, data),

  updateCondition: (batchId, condition, notes = null) =>
    api.patch(`${BASE}/${batchId}/condition`, { condition, notes }),

  cull: (batchId, qty, notes = null) =>
    api.post(`${BASE}/${batchId}/cull`, { qty, notes }),

  transfer: (batchId, warehouseLocationId, notes = null) =>
    api.patch(`${BASE}/${batchId}/transfer`, {
      warehouse_location_id: warehouseLocationId,
      notes,
    }),
};

// ── Location API ─────────────────────────────────────────────────────────────

export const warehouseLocationService = {
  list: (params = {}) => api.get(LOC, { params }),
  find: (id) => api.get(`${LOC}/${id}`),
  create: (data) => api.post(LOC, data),
  update: (id, data) => api.patch(`${LOC}/${id}`, data),
  toggle: (id) => api.patch(`${LOC}/${id}/toggle`),
  destroy: (id) => api.delete(`${LOC}/${id}`),
};

// ── Condition helpers ────────────────────────────────────────────────────────

export const CONDITIONS = ["fresh", "aging", "wilting", "spoiled", "discarded"];

export const CONDITION_META = {
  fresh: {
    label: "Fresh",
    bg: "#dcfce7",
    text: "#166534",
    dot: "#16a34a",
    border: "#a7f3d0",
  },
  aging: {
    label: "Aging",
    bg: "#fef9c3",
    text: "#854d0e",
    dot: "#ca8a04",
    border: "#fde047",
  },
  wilting: {
    label: "Wilting",
    bg: "#ffedd5",
    text: "#9a3412",
    dot: "#ea580c",
    border: "#fdba74",
  },
  spoiled: {
    label: "Spoiled",
    bg: "#fee2e2",
    text: "#991b1b",
    dot: "#dc2626",
    border: "#fca5a5",
  },
  discarded: {
    label: "Discarded",
    bg: "#f3f4f6",
    text: "#4b5563",
    dot: "#9ca3af",
    border: "#d1d5db",
  },
  unknown: {
    label: "Unknown",
    bg: "#f3f4f6",
    text: "#4b5563",
    dot: "#9ca3af",
    border: "#d1d5db",
  },
};

export function conditionMeta(condition) {
  return CONDITION_META[condition] ?? CONDITION_META.unknown;
}

export function daysRemainingLabel(days) {
  if (days === null || days === undefined) return "—";
  if (days < 0) return `Expired ${Math.abs(days)}d ago`;
  if (days === 0) return "Expires today";
  if (days === 1) return "1 day left";
  return `${days} days left`;
}

export function daysRemainingClass(days) {
  if (days === null || days === undefined) return "";
  if (days <= 0) return "danger";
  if (days <= 2) return "warn-red";
  if (days <= 5) return "warn";
  return "ok";
}
