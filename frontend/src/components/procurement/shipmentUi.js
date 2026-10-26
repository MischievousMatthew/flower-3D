// Shared wording for supplier shipments (Logistics).

export const SHIP_STATUS = {
  pending: { label: "Not shipped yet", tone: "amber", icon: "clock" },
  in_transit: { label: "On the way", tone: "blue", icon: "truck" },
  out_for_delivery: { label: "Out for delivery", tone: "violet", icon: "pin" },
  delivered: { label: "Delivered", tone: "green", icon: "check" },
  failed: { label: "Failed", tone: "red", icon: "alert" },
  returned: { label: "Returned", tone: "slate", icon: "restore" },
};

export const SHIP_STEPS = ["pending", "in_transit", "out_for_delivery", "delivered"];

export const shipStatus = (s) => SHIP_STATUS[s] ?? { label: s, tone: "slate", icon: "truck" };
