// Shared wording for purchase orders: the five stages people see, the stored
// statuses inside them, and the lines of an order's history.

export const PO_STAGES = [
  { key: "draft", label: "Draft", tone: "slate", icon: "edit", hint: "Still being written" },
  { key: "pending_approval", label: "Pending approval", tone: "amber", icon: "clock", hint: "Waiting for someone to approve" },
  { key: "ordered", label: "Ordered", tone: "blue", icon: "send", hint: "With the supplier" },
  { key: "partially_received", label: "Partially received", tone: "violet", icon: "box", hint: "Some of it has arrived" },
  { key: "completed", label: "Completed", tone: "green", icon: "check", hint: "Everything arrived" },
];

const STAGE_OF = {
  draft: "draft",
  pending_approval: "pending_approval",
  pending: "ordered",
  processing: "ordered",
  shipped: "ordered",
  received: "ordered",
  partially_received: "partially_received",
  completed: "completed",
};

export const stageOf = (status) => PO_STAGES.find((s) => s.key === STAGE_OF[status]) ?? PO_STAGES[2];

/** Where an "Ordered" order is right now, in words */
export const ORDERED_STEP = {
  pending: "Sent to supplier",
  processing: "Supplier preparing",
  shipped: "On the way",
  received: "Arrived, not in stock yet",
};

export const statusText = (status) => ORDERED_STEP[status] ?? stageOf(status).label;

export const isEditable = (status) => status === "draft" || status === "pending_approval";
export const isReceivable = (status) => ["pending", "processing", "shipped", "received", "partially_received"].includes(status);

/** The next existing step (shipments, scanning) an Ordered order can move to */
export const NEXT_STEPS = {
  pending: [{ status: "processing", label: "Supplier is preparing it" }],
  processing: [{ status: "shipped", label: "It's on the way" }],
  shipped: [{ status: "received", label: "It arrived" }],
};

/** One line of history in words */
export function eventText(e) {
  switch (e.event) {
    case "created":
      return e.to_status === "draft" ? "Started as a draft" : e.to_status === "pending_approval" ? "Created and sent for approval" : "Created and sent to the supplier";
    case "edited":
      return "Changed the order";
    case "approved":
      return "Approved the order";
    case "sent_back":
      return "Sent it back to draft";
    case "emailed":
      return "Emailed it to the supplier";
    case "email_failed":
      return "Tried to email the supplier, but it didn't go through";
    case "received":
      return e.to_status === "completed" ? "Received the last of it" : "Received part of it";
    case "inspected":
      return e.to_status === "completed" ? "Received and inspected the last delivery" : "Received and inspected a delivery";
    case "status":
      if (e.to_status === "pending_approval") return "Sent it for approval";
      if (e.to_status === "completed") return "Marked it completed";
      return `Moved it to ${statusText(e.to_status).toLowerCase()}`;
    default:
      return e.event;
  }
}

export const EVENT_TONE = { inspected: "violet", approved: "green", emailed: "blue", sent_back: "amber", email_failed: "red", received: "violet", created: "slate", edited: "slate", status: "slate" };

export const poDate = (d) => (d ? new Date(String(d).length === 10 ? `${d}T00:00:00` : d).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—");
export const poDateTime = (d) => (d ? new Date(d).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "—");

export const todayYmd = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
};

/** Days until (negative: since) a YYYY-MM-DD date */
export function daysUntil(ymd) {
  if (!ymd) return null;
  const a = new Date(`${String(ymd).slice(0, 10)}T00:00:00`);
  const b = new Date(`${todayYmd()}T00:00:00`);
  return Math.round((a - b) / 86400000);
}

/** "Due today", "Due in 3 days", "2 days late" */
export function dueText(expected, status) {
  if (!expected || status === "completed") return "";
  const n = daysUntil(expected);
  if (n === null) return "";
  if (!isReceivable(status)) return n < 0 ? "Expected date has passed" : "";
  if (n < 0) return `${-n} ${n === -1 ? "day" : "days"} late`;
  if (n === 0) return "Due today";
  return n === 1 ? "Due tomorrow" : `Due in ${n} days`;
}
