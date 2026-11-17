// An order's status as everyone sees it: the shop's Orders page, the customer's
// My Orders, the ERP and the admin console. The server works it out
// (Order::displayStatus) and sends `status` with these keys and `status_label`.
export const ORDER_STATUS_LABELS = {
  pending: "Pending",
  to_processed: "Preparing",
  to_ship: "Ready",
  to_received: "Out for delivery",
  completed: "Completed",
  cancelled: "Cancelled",
  returned: "Returned",
  refunded: "Refunded",
};

// Chart colours for the same statuses, in workflow order
export const ORDER_STATUS_COLORS = {
  pending: "#f59e0b",
  to_processed: "#3b82f6",
  to_ship: "#ec4899",
  to_received: "#38bdf8",
  completed: "#22c55e",
  cancelled: "#cbd5e1",
  returned: "#a78bfa",
  refunded: "#8b5cf6",
};

/** "Out for delivery" for an order (or a status key); the server's words come first */
export function orderStatusLabel(orderOrKey) {
  if (orderOrKey && typeof orderOrKey === "object") {
    return orderOrKey.status_label || ORDER_STATUS_LABELS[orderOrKey.status] || humanize(orderOrKey.status);
  }
  return ORDER_STATUS_LABELS[orderOrKey] || humanize(orderOrKey);
}

const humanize = (value) => String(value ?? "").replace(/_/g, " ").replace(/^./, (c) => c.toUpperCase());
