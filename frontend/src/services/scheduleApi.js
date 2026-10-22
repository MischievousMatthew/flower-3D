// The shift planner's API. The ERP and the vendor portal use the same endpoints under
// different prefixes ("schedules" for HR, "vendor/staff/planner" for the shop owner).
import api from "../plugins/axios";

export function scheduleApi(prefix) {
  const res = (p) => p.then((r) => r.data);
  return {
    board: async (from, to) => (await api.get(`${prefix}/board`, { params: { from, to } })).data.data,
    assign: (body) => res(api.post(`${prefix}/assign`, body)),
    replace: (id, body) => res(api.post(`${prefix}/shifts/${id}/replace`, body)),
    undoReplace: (id) => res(api.post(`${prefix}/shifts/${id}/undo-replace`)),
    remove: (id) => res(api.delete(`${prefix}/shifts/${id}`)),
    createTemplate: (body) => res(api.post(`${prefix}/templates`, body)),
    updateTemplate: (id, body) => res(api.put(`${prefix}/templates/${id}`, body)),
    deleteTemplate: (id) => res(api.delete(`${prefix}/templates/${id}`)),
    link: (id, body) => res(api.post(`${prefix}/templates/${id}/link`, body)),
    copyWeek: (week) => res(api.post(`${prefix}/copy-week`, { week })),
  };
}
