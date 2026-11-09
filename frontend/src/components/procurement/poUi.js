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
  cancelled: "cancelled",
};

/** Not one of the five steps: an order stopped before anything arrived */
export const CANCELLED_STAGE = { key: "cancelled", label: "Cancelled", tone: "red", icon: "close", hint: "Stopped before anything arrived" };

/** The stages to filter the order list by: the five steps, plus cancelled */
export const PO_FILTER_STAGES = [...PO_STAGES, CANCELLED_STAGE];

export const stageOf = (status) => PO_FILTER_STAGES.find((s) => s.key === STAGE_OF[status]) ?? PO_STAGES[2];

/** Can still be cancelled: nothing has arrived yet */
export const isCancellable = (o) =>
  ["draft", "pending_approval", "pending", "processing", "shipped"].includes(o?.status) && !(o?.items ?? []).some((i) => Number(i.received_qty) > 0);

/** Where an "Ordered" order is right now, in words */
export const ORDERED_STEP = {
  pending: "Waiting for the supplier",
  processing: "Supplier approved",
  shipped: "On the way",
  received: "Arrived, not in stock yet",
};

export const statusText = (status) => ORDERED_STEP[status] ?? stageOf(status).label;

export const isEditable = (status) => status === "draft" || status === "pending_approval";
// Only once the supplier has approved it: nothing can arrive before that
export const isReceivable = (status) => ["processing", "shipped", "received", "partially_received"].includes(status);

/** The next existing step (shipments, scanning) an Ordered order can move to */
export const NEXT_STEPS = {
  pending: [{ status: "processing", label: "The supplier approved it (they told us by phone or message)" }],
  processing: [{ status: "shipped", label: "It's on the way" }],
  shipped: [{ status: "received", label: "It arrived" }],
};

const pesoFmt = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
const RANK = { draft: 0, pending_approval: 0, pending: 1, processing: 2, shipped: 3, received: 4, partially_received: 4, completed: 5 };

/**
 * What has really happened to an order, step by step: drafted, (approved,)
 * sent to the supplier, approved by the supplier, on the way, received,
 * paid (orders paid through a funding request) and completed. The first step
 * not done yet is the current one.
 */
export function orderSteps(o) {
  if (!o) return [];
  const rank = RANK[o.status] ?? 0;
  const pay = o.funding?.payment && !o.funding.payment.legacy ? o.funding.payment : null;
  const items = o.items ?? [];
  const units = items.reduce((t, i) => t + Number(i.quantity || 0), 0);
  const inStock = items.reduce((t, i) => t + Number(i.received_qty || 0), 0);
  const confirmedLess = items.some((i) => Number(i.cancelled_qty) > 0) && !!o.supplier_confirmed_at;
  const steps = [];

  steps.push({ key: "draft", label: "Draft", note: o.funding ? `Finance approved · ${o.funding.finance_request_id}` : o.created_by_name ? `by ${o.created_by_name}` : "", done: rank >= 1 || (!o.funding && o.status === "pending_approval") });
  // Orders not paid through a funding request are approved inside the shop first
  if (!o.funding) {
    steps.push({ key: "approval", label: "Approved", note: o.approved_at && rank >= 1 ? poDate(o.approved_at) : o.status === "pending_approval" ? "Waiting for approval" : "", done: rank >= 1 });
  }
  steps.push({ key: "sent", label: "Sent to supplier", note: o.sent_at ? poDate(o.sent_at) : rank >= 1 ? "Sent another way" : "Not sent yet", done: rank >= 1 });
  steps.push({
    key: "supplier",
    label: "Supplier approval",
    note: rank >= 2 ? (o.supplier_confirmed_at ? `Approved ${poDate(o.supplier_confirmed_at)}${confirmedLess ? " · less than ordered" : ""}` : "Approved") : rank === 1 ? "Waiting for them" : "",
    done: rank >= 2,
  });
  const onTheWay = { key: "shipped", label: "On the way", note: o.supplier_shipped_at ? poDate(o.supplier_shipped_at) : rank >= 3 ? "Yes" : "", done: rank >= 3 };
  const received = {
    key: "received",
    label: o.status === "partially_received" ? "Partly received" : "Received",
    note: o.status === "completed" ? (o.completed_at ? poDate(o.completed_at) : "All in") : inStock && units ? `${Math.round((inStock / units) * 100)}% in` : "",
    done: o.status === "completed",
  };
  const paid = pay
    ? {
        key: "paid",
        label: "Paid",
        note: pay.stage === "to_pay" ? `${pesoFmt.format(pay.to_pay)} to pay` : pay.net_paid > 0 ? pesoFmt.format(pay.net_paid) : pay.timing === "before_delivery" ? "Once they approve" : "After delivery",
        done: ["paid", "supplier_owes"].includes(pay.stage),
      }
    : null;

  if (paid && pay.timing === "before_delivery") steps.push(paid, onTheWay, received);
  else steps.push(onTheWay, received, ...(paid ? [paid] : []));
  steps.push({ key: "completed", label: "Completed", note: "", done: o.status === "completed" && (!paid || paid.done) });

  const current = steps.findIndex((x) => !x.done);
  return steps.map((x, i) => ({ ...x, state: x.done ? "done" : i === current ? "now" : "" }));
}

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
    case "cancelled":
      return "Cancelled the order";
    case "cancel_emailed":
      return "Told the supplier it's cancelled";
    case "paid":
      return "Finance paid the supplier";
    case "paid_emailed":
      return "Told the supplier it was paid";
    case "supplier_payout":
      return "Supplier changed their payment account";
    case "supplier_confirmed":
      return "Supplier confirmed the order";
    case "supplier_declined":
      return "Supplier can't supply it; back to draft";
    case "supplier_shipped":
      return "Supplier sent it on its way";
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

export const EVENT_TONE = { inspected: "violet", approved: "green", emailed: "blue", sent_back: "amber", email_failed: "red", received: "violet", created: "slate", edited: "slate", status: "slate", supplier_confirmed: "green", supplier_declined: "red", supplier_shipped: "blue", cancelled: "red", cancel_emailed: "blue", paid: "green", paid_emailed: "blue", supplier_payout: "slate" };

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
