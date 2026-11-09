// Where an approved funding request's money stands (FundingPaymentService::state)

const pesoFmt = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
export const peso = (n) => pesoFmt.format(Number(n || 0));

export const timingText = (p) =>
  p?.timing === "before_delivery" ? "Paid before delivery, once the supplier confirms" : "Paid after delivery, for what arrives";

export function paymentStage(p) {
  if (!p) return null;
  const terms = p.supplier_terms ? ` (${p.supplier_terms})` : "";
  switch (p.stage) {
    case "needs_order":
      return { key: p.stage, label: "Needs a purchase order", tone: "slate", icon: "clock", text: "Procurement orders it from a supplier next. Nothing is paid until then." };
    case "with_supplier":
      return { key: p.stage, label: "Waiting for the supplier", tone: "slate", icon: "clock", text: `Sent to ${p.order?.supplier_name || "the supplier"}. ${timingText(p)}${terms}.` };
    case "awaiting_delivery":
      return { key: p.stage, label: "Pay after delivery", tone: "blue", icon: "truck", text: `${p.order?.supplier_name || "The supplier"} is paid once the stock is in${terms}.` };
    case "to_pay":
      return {
        key: p.stage,
        label: `To pay ${peso(p.to_pay)}`,
        tone: p.overdue ? "red" : "amber",
        icon: "wallet",
        text: p.timing === "before_delivery"
          ? `${p.order?.supplier_name || "The supplier"} confirmed what they can supply. Pay before they deliver${terms}.`
          : `The stock is in. Pay for what arrived${p.due_date ? `, by ${dateText(p.due_date)}` : ""}${terms}.`,
      };
    case "paid":
      return { key: p.stage, label: "Paid", tone: "teal", icon: "check", text: p.legacy ? "Paid from the shop balance when it was approved." : `${peso(p.net_paid ?? p.paid)} paid to ${p.order?.supplier_name || "the supplier"}.` };
    case "supplier_owes":
      return { key: p.stage, label: `Supplier owes ${peso(p.supplier_owes)}`, tone: "red", icon: "alert", text: "They were paid for more than they delivered. Record the refund, or keep it as credit for a later order." };
    case "cancelled":
      return { key: p.stage, label: "Order cancelled", tone: "slate", icon: "close", text: "The purchase order was cancelled before anything was paid." };
    default:
      return { key: p.stage, label: "Nothing to pay", tone: "slate", icon: "check", text: "Nothing is due on this request." };
  }
}

export const dateText = (d) => (d ? new Date(String(d).length === 10 ? `${d}T00:00:00` : d).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "—");

export const PAYMENT_KIND = {
  payment: { label: "Paid to supplier", tone: "teal" },
  refund: { label: "Refunded by supplier", tone: "green" },
  credit: { label: "Kept as credit", tone: "blue" },
};
