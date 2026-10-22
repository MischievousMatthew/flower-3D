// Payroll for HR and the shop owner (create, revise) and Finance (approve, send back, pay).
// Finance reads through its own endpoints because it has its own permission (payroll_requests).
import api from "../plugins/axios";

const data = (p) => p.then((r) => r.data);

const shared = {
  approve: (ids, finance_notes) => data(api.post("/payroll/finance-approve", { ids, finance_notes })),
  reject: (ids, finance_notes) => data(api.post("/payroll/finance-reject", { ids, finance_notes })),
  markPaid: (ids) => data(api.post("/payroll/mark-paid", { ids })),
};

export const hrPayroll = {
  ...shared,
  list: (params) => data(api.get("/payroll", { params })),
  summary: (params) => data(api.get("/payroll/summary", { params })),
  payslip: (id) => data(api.get(`/payroll/${id}`)),
  people: () => data(api.get("/payroll/people")),
  preview: (body) => data(api.post("/payroll/preview", body)),
  batchPreview: (body) => data(api.post("/payroll/batch-preview", body)),
  batch: (body) => data(api.post("/payroll/batch", body)),
  revise: (id, body) => data(api.put(`/payroll/${id}`, body)),
  remove: (id) => data(api.delete(`/payroll/${id}`)),
  components: (employee_id) => data(api.get("/payroll/components", { params: { employee_id } })),
  saveComponent: (body) => data(body.id ? api.put(`/payroll/components/${body.id}`, body) : api.post("/payroll/components", body)),
  removeComponent: (id) => data(api.delete(`/payroll/components/${id}`)),
  salaryHistory: (employeeId) => data(api.get(`/payroll/salary-history/${employeeId}`)),
};

export const financePayroll = {
  ...shared,
  list: (params) => data(api.get("/payroll/finance-requests", { params })),
  summary: (params) => data(api.get("/payroll/finance-summary", { params })),
  payslip: (id) => data(api.get(`/payroll/finance/${id}`)),
};

export const PAYROLL_STATUS = {
  pending: { label: "Waiting for Finance", short: "Waiting", tone: "amber" },
  rejected: { label: "Sent back", short: "Sent back", tone: "red" },
  approved: { label: "Approved — ready to pay", short: "Approved", tone: "blue" },
  paid: { label: "Paid", short: "Paid", tone: "green" },
};

export const PAYROLL_EVENT = {
  created: "Created",
  revised: "Recalculated",
  resubmitted: "Fixed and sent again",
  approved: "Approved",
  rejected: "Sent back",
  paid: "Paid",
};

const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
export const php = (v) => peso.format(Number(String(v ?? 0).replace(/,/g, "")) || 0);
export const num = (v) => Number(String(v ?? 0).replace(/,/g, "")) || 0;

const pad = (n) => String(n).padStart(2, "0");
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

/** Common pay periods around today */
export function periodPresets(today = new Date()) {
  const y = today.getFullYear();
  const m = today.getMonth();
  const monthStart = new Date(y, m, 1);
  const monthEnd = new Date(y, m + 1, 0);
  const prevStart = new Date(y, m - 1, 1);
  const prevEnd = new Date(y, m, 0);
  const mon = new Date(today);
  mon.setDate(today.getDate() - ((today.getDay() + 6) % 7) - 7);
  const sun = new Date(mon);
  sun.setDate(mon.getDate() + 6);
  const firstHalf = today.getDate() > 15;
  return [
    firstHalf
      ? { key: "half", label: `1–15 ${monthStart.toLocaleDateString("en-PH", { month: "short" })}`, from: ymd(monthStart), to: ymd(new Date(y, m, 15)) }
      : { key: "half", label: `16–${prevEnd.getDate()} ${prevEnd.toLocaleDateString("en-PH", { month: "short" })}`, from: ymd(new Date(y, m - 1, 16)), to: ymd(prevEnd) },
    { key: "week", label: "Last week", from: ymd(mon), to: ymd(sun) },
    { key: "month", label: prevStart.toLocaleDateString("en-PH", { month: "long" }), from: ymd(prevStart), to: ymd(prevEnd) },
    { key: "this_month", label: monthStart.toLocaleDateString("en-PH", { month: "long" }), from: ymd(monthStart), to: ymd(monthEnd) },
  ];
}

export const periodText = (a, b) => {
  const f = (s, o) => new Date(`${s}T00:00:00`).toLocaleDateString("en-PH", o);
  return `${f(a, { month: "short", day: "numeric" })} – ${f(b, { month: "short", day: "numeric", year: "numeric" })}`;
};
