// Leave & overtime: the public leave page (session token in the body, never the URL)
// and the HR / shop owner desk. Both reach the same requests.
import api from "../plugins/axios";

const data = (p) => p.then((r) => r.data);

export const publicLeave = {
  shop: (shop) => data(api.post("/public/leave/shop", { shop })),
  identify: (shop, badge) => data(api.post("/public/leave/identify", { shop, badge })),
  me: (token) => data(api.post("/public/leave/me", { token })),
  file(token, form) {
    const body = new FormData();
    body.append("token", token);
    for (const k of ["leave_type", "start_date", "end_date", "reason"]) body.append(k, form[k]);
    if (form.document) body.append("document", form.document);
    return data(api.post("/public/leave/requests", body, { headers: { "Content-Type": "multipart/form-data" } }));
  },
  cancel: (token, id) => data(api.post(`/public/leave/requests/${id}/cancel`, { token })),
  overtime: (token, form) => data(api.post("/public/leave/overtime", { token, ...form })),
  cancelOvertime: (token, id) => data(api.post(`/public/leave/overtime/${id}/cancel`, { token })),
};

export const leaveDesk = {
  list: (params = {}) => data(api.get("/leaves", { params })),
  stats: () => data(api.get("/leaves/statistics")),
  decide: (id, status, admin_notes) => data(api.put(`/leaves/${id}/status`, { status, admin_notes })),
  cancel: (id, note) => data(api.post(`/leaves/${id}/cancel`, { note })),
  document: async (id) => (await api.get(`/leaves/${id}/document`)).data.data.url,
  file(form) {
    const body = new FormData();
    for (const k of ["employee_id", "leave_type", "start_date", "end_date", "reason"]) body.append(k, form[k]);
    if (form.document) body.append("document", form.document);
    return data(api.post("/leaves", body, { headers: { "Content-Type": "multipart/form-data" } }));
  },
  balances: (year) => data(api.get("/leaves/balances", { params: { year } })),
  adjust: (body) => data(api.post("/leaves/balances/adjust", body)),
  policies: () => data(api.get("/leaves/policies")),
  savePolicies: (policies) => data(api.put("/leaves/policies", { policies })),
  poster: () => data(api.get("/leaves/poster")),
  overtime: (params = {}) => data(api.get("/overtime-requests", { params })),
  fileOvertime: (body) => data(api.post("/overtime-requests", body)),
  decideOvertime: (id, status, note) => data(api.put(`/overtime-requests/${id}/status`, { status, note })),
  cancelOvertime: (id) => data(api.post(`/overtime-requests/${id}/cancel`)),
};

/** Shared wording */
export const LEAVE_STATUS = {
  pending: { label: "Waiting", tone: "amber" },
  approved: { label: "Approved", tone: "green" },
  rejected: { label: "Not approved", tone: "red" },
  cancelled: { label: "Cancelled", tone: "slate" },
};
export const EVENT_LABEL = {
  filed: "Filed",
  approved: "Approved",
  rejected: "Not approved",
  cancelled: "Cancelled",
};
export const durationText = (m) => (m >= 60 ? `${Math.floor(m / 60)} h${m % 60 ? ` ${m % 60} min` : ""}` : `${m} min`);
export const daysText = (n) => (n === null || n === undefined ? "No limit" : `${Number(n) % 1 ? Number(n).toFixed(1) : Number(n)} ${Number(n) === 1 ? "day" : "days"}`);
export const dateRange = (a, b) => {
  const f = (s, opts) => new Date(`${s}T00:00:00`).toLocaleDateString("en-PH", opts);
  if (a === b) return f(a, { weekday: "short", month: "short", day: "numeric", year: "numeric" });
  return `${f(a, { month: "short", day: "numeric" })} – ${f(b, { month: "short", day: "numeric", year: "numeric" })}`;
};
