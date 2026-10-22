// Employment documents on an HR profile. Shared by the vendor portal and the ERP.
import api from "../plugins/axios";

export const employeeDocumentService = {
  async list(profileId) {
    const { data } = await api.get(`/employees-info/${profileId}/documents`);
    return data;
  },

  async upload(profileId, { file, category, title, expiresOn }) {
    const body = new FormData();
    body.append("file", file);
    body.append("category", category);
    if (title) body.append("title", title);
    if (expiresOn) body.append("expires_on", expiresOn);
    const { data } = await api.post(`/employees-info/${profileId}/documents`, body, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    return data;
  },

  // The files are private; this returns a link that works for a few minutes
  async link(profileId, documentId) {
    const { data } = await api.get(`/employees-info/${profileId}/documents/${documentId}/link`);
    return data.data.url;
  },

  async remove(profileId, documentId) {
    const { data } = await api.delete(`/employees-info/${profileId}/documents/${documentId}`);
    return data;
  },
};

export default employeeDocumentService;
