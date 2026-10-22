<template>
  <div class="ed">
    <form v-if="canEdit" class="ed-add" novalidate @submit.prevent="upload">
      <label class="ed-file" :class="{ picked: file }">
        <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" @change="pick" />
        <span>{{ file ? file.name : "Choose a file (PDF, image or Word, up to 10 MB)" }}</span>
      </label>
      <div class="ed-row">
        <select v-model="category" class="ed-input" aria-label="Kind of document">
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
        <input v-model="title" class="ed-input" maxlength="150" placeholder="Name (optional)" aria-label="Document name" />
      </div>
      <div class="ed-row">
        <label class="ed-exp">
          <span>Expires on <small>(optional)</small></span>
          <input v-model="expiresOn" type="date" class="ed-input" />
        </label>
        <button type="submit" class="ed-btn" :disabled="!file || uploading">{{ uploading ? "Uploading…" : "Add document" }}</button>
      </div>
      <p v-if="error" class="ed-error">{{ error }}</p>
    </form>

    <ul v-if="documents === null" class="ed-list">
      <li v-for="n in 2" :key="n" class="ed-item"><div class="bc-skeleton ed-sk"></div></li>
    </ul>
    <p v-else-if="loadError" class="ed-error">{{ loadError }} <button type="button" class="ed-link" @click="load">Try again</button></p>
    <p v-else-if="!documents.length" class="ed-empty">No documents yet.{{ canEdit ? " Add contracts, IDs or certificates above." : "" }}</p>
    <ul v-else class="ed-list">
      <li v-for="d in documents" :key="d.id" class="ed-item">
        <span class="ed-ext">{{ (d.format || "file").slice(0, 4).toUpperCase() }}</span>
        <div class="ed-main">
          <strong>{{ d.title }}</strong>
          <small>
            {{ d.category }}<template v-if="d.size"> · {{ size(d.size) }}</template> · by {{ d.uploaded_by || "someone" }}, {{ day(d.uploaded_at) }}
          </small>
          <small v-if="d.expires_on" :class="{ 'ed-expired': d.expires_on < today }">
            {{ d.expires_on < today ? "Expired" : "Expires" }} {{ day(d.expires_on) }}
          </small>
        </div>
        <button type="button" class="ed-link" :disabled="opening === d.id" @click="open(d)">{{ opening === d.id ? "Opening…" : "Open" }}</button>
        <button v-if="canEdit" type="button" class="ed-link danger" :disabled="removing === d.id" @click="remove(d)">
          {{ confirmId === d.id ? "Confirm" : removing === d.id ? "Removing…" : "Remove" }}
        </button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { onMounted, ref, watch } from "vue";
import employeeDocumentService from "../../services/employeeDocumentService";

const props = defineProps({
  profileId: { type: Number, required: true },
  canEdit: { type: Boolean, default: false },
});
const emit = defineEmits(["changed"]);

const documents = ref(null);
const categories = ref(["Contract", "Government ID", "Resume", "Certificate", "Clearance", "Medical", "Other"]);
const loadError = ref("");
const error = ref("");
const file = ref(null);
const fileInput = ref(null);
const category = ref("Contract");
const title = ref("");
const expiresOn = ref("");
const uploading = ref(false);
const opening = ref(null);
const removing = ref(null);
const confirmId = ref(null);
const today = new Date().toISOString().slice(0, 10);

const message = (e, fallback) => {
  const errors = e?.response?.data?.errors;
  const first = errors ? Object.values(errors).flat()[0] : null;
  return first || e?.response?.data?.message || fallback;
};
const size = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
const day = (iso) => (iso ? new Date(iso.length === 10 ? `${iso}T00:00:00` : iso).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "");

async function load() {
  loadError.value = "";
  documents.value = null;
  try {
    const res = await employeeDocumentService.list(props.profileId);
    documents.value = res.data;
    if (res.categories?.length) categories.value = res.categories;
  } catch (e) {
    documents.value = [];
    loadError.value = message(e, "Couldn't load the documents.");
  }
}

function pick(event) {
  error.value = "";
  const f = event.target.files?.[0] ?? null;
  if (f && f.size > 10 * 1024 * 1024) {
    error.value = "Files can be up to 10 MB.";
    event.target.value = "";
    file.value = null;
    return;
  }
  file.value = f;
}

async function upload() {
  if (!file.value || uploading.value) return;
  uploading.value = true;
  error.value = "";
  try {
    const res = await employeeDocumentService.upload(props.profileId, { file: file.value, category: category.value, title: title.value.trim(), expiresOn: expiresOn.value });
    documents.value = [res.data, ...(documents.value ?? [])];
    file.value = null;
    title.value = "";
    expiresOn.value = "";
    if (fileInput.value) fileInput.value.value = "";
    emit("changed", documents.value.length);
  } catch (e) {
    error.value = message(e, "Couldn't upload the file.");
  } finally {
    uploading.value = false;
  }
}

async function open(d) {
  // Open the tab now, while we still have the click, so it isn't blocked as a popup
  const win = window.open("", "_blank");
  opening.value = d.id;
  try {
    const url = await employeeDocumentService.link(props.profileId, d.id);
    if (win) {
      win.opener = null;
      win.location.href = url;
    } else {
      window.location.href = url;
    }
  } catch (e) {
    win?.close();
    error.value = message(e, "Couldn't open the file.");
  } finally {
    opening.value = null;
  }
}

async function remove(d) {
  if (confirmId.value !== d.id) {
    confirmId.value = d.id;
    return;
  }
  confirmId.value = null;
  removing.value = d.id;
  try {
    await employeeDocumentService.remove(props.profileId, d.id);
    documents.value = documents.value.filter((x) => x.id !== d.id);
    emit("changed", documents.value.length);
  } catch (e) {
    error.value = message(e, "Couldn't remove the file.");
  } finally {
    removing.value = null;
  }
}

watch(() => props.profileId, load);
onMounted(load);
</script>

<style scoped>
.ed {
  display: flex;
  flex-direction: column;
  gap: 12px;
  color: var(--v-text, #2d3748);
}
.ed-add {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 12px;
  border: 1px dashed var(--v-border-strong, #cbd5e0);
  border-radius: 12px;
}
.ed-file {
  position: relative;
  display: block;
  padding: 10px 12px;
  border-radius: 10px;
  background: var(--v-subtle, #f7fafc);
  font-size: 13px;
  color: var(--v-muted, #718096);
  cursor: pointer;
  overflow-wrap: anywhere;
}
.ed-file.picked {
  color: var(--v-text, #2d3748);
  font-weight: 600;
}
.ed-file input {
  position: absolute;
  inset: 0;
  opacity: 0;
  cursor: pointer;
}
.ed-file:has(input:focus-visible) {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--bc-accent, #4299e1) 30%, transparent);
}
.ed-row {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 8px;
}
.ed-row > * {
  flex: 1 1 160px;
  min-width: 0;
}
.ed-input {
  width: 100%;
  height: 38px;
  padding: 0 10px;
  border: 1px solid var(--v-border-strong, #cbd5e0);
  border-radius: 10px;
  background: var(--v-input, #fff);
  color: inherit;
  font: inherit;
  font-size: 13px;
}
.ed-exp {
  display: flex;
  flex-direction: column;
  gap: 4px;
  font-size: 12px;
  color: var(--v-muted, #718096);
}
.ed-btn {
  flex: 0 0 auto !important;
  height: 38px;
  padding: 0 16px;
  border: none;
  border-radius: 10px;
  background: var(--bc-accent, #4299e1);
  color: #fff;
  font: inherit;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.ed-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}
.ed-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.ed-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border: 1px solid var(--v-border, #e2e8f0);
  border-radius: 12px;
}
.ed-sk {
  width: 100%;
  height: 34px;
  border-radius: 8px;
}
.ed-ext {
  flex: none;
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: var(--v-subtle, #edf2f7);
  font-size: 10px;
  font-weight: 700;
  color: var(--v-muted, #4a5568);
}
.ed-main {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}
.ed-main strong {
  font-size: 13px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.ed-main small {
  font-size: 11.5px;
  color: var(--v-muted, #718096);
}
.ed-main .ed-expired {
  color: #e53e3e;
  font-weight: 600;
}
.ed-link {
  flex: none;
  padding: 4px 6px;
  border: none;
  background: none;
  color: var(--bc-accent, #3182ce);
  font: inherit;
  font-size: 12.5px;
  font-weight: 600;
  cursor: pointer;
}
.ed-link.danger {
  color: #e53e3e;
}
.ed-link:disabled {
  opacity: 0.6;
  cursor: default;
}
.ed-empty {
  margin: 0;
  font-size: 13px;
  color: var(--v-muted, #718096);
}
.ed-error {
  margin: 0;
  font-size: 12.5px;
  color: #e53e3e;
}
</style>
