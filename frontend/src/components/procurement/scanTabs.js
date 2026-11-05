// Order Scan has one scanner for every step, and the history of finished deliveries
export const SCAN_TABS = [
  { name: "ScanToProcess", label: "Scanner", icon: "eye" },
  { name: "ScanCompleted", label: "Completed", icon: "check" },
];

// Where a delivery is, in the words the shop uses, and what one scan does to it
export const SCAN_STEPS = {
  pending: { label: "To process", next: "to_processed", tone: "amber", icon: "box" },
  to_processed: { label: "To ship", next: "to_ship", tone: "blue", icon: "truck" },
  to_ship: { label: "On the way", next: "to_received", tone: "violet", icon: "pin" },
  to_received: { label: "To receive", next: null, tone: "green", icon: "check" },
  completed: { label: "Completed", next: null, tone: "green", icon: "check" },
};
export const stepLabel = (status) => SCAN_STEPS[status]?.label ?? status;
