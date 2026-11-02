<template>
  <AuthShell
    title="Update your documents"
    :lead="lead"
  >
    <template #top>
      Already approved? <router-link to="/guest/login" class="af-link">Sign in</router-link>
    </template>
    <template #caption>
      <i>Almost there.</i><br />
      Send the corrected item and our review team takes it from here.
    </template>

    <!-- Loading -->
    <div v-if="loading" class="rs-list" aria-busy="true" aria-label="Loading your requested documents">
      <div v-for="n in 2" :key="n" class="rs-item">
        <div class="bc-skeleton rs-sk rs-sk--title"></div>
        <div class="bc-skeleton rs-sk rs-sk--line"></div>
        <div class="bc-skeleton rs-sk rs-sk--drop"></div>
      </div>
    </div>

    <!-- The link can't be used -->
    <AuthNotice
      v-else-if="loadError"
      tone="warn"
      title="This link can't be used"
      :body="loadError"
    >
      <span>Check your email for a newer link. If you can't find one, reply to our email and our team will send a fresh link.</span>
    </AuthNotice>

    <!-- Sent -->
    <section v-else-if="!pendingItems.length" class="rs-done" aria-live="polite">
      <span class="rs-done-mark" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="m6 12.5 4 4 8-9" /></svg>
      </span>
      <h2>{{ justSent ? "Thank you, it's on its way" : "Nothing left to send" }}</h2>
      <p>Our review team has your update and usually checks it within two working days. We'll email you as soon as it's reviewed.</p>
      <ul class="rs-sent" aria-label="What you sent">
        <li v-for="item in items" :key="item.id">
          <span>{{ item.label }}</span>
          <b>{{ item.resubmitted_at ? `Received ${fmtDate(item.resubmitted_at)}` : "Received" }}</b>
        </li>
      </ul>
      <router-link to="/" class="af-btn ghost rs-home">Back to BloomCraft</router-link>
    </section>

    <!-- Form -->
    <form v-else class="af-form" novalidate @submit.prevent="submit">
      <p v-if="expiresLabel" class="af-note rs-expiry">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
        This link works until {{ expiresLabel }}.
      </p>

      <article v-for="item in pendingItems" :key="item.id" class="rs-item">
        <header class="rs-item-head">
          <h2 :id="`rs-${item.id}`">{{ item.label }}</h2>
          <span class="rs-chip">Needs an update</span>
        </header>

        <div v-if="item.rejection_reason" class="rs-reason">
          <span class="rs-reason-lbl">From our reviewer</span>
          <p>{{ item.rejection_reason }}</p>
        </div>

        <DocDrop
          v-if="item.type === 'file'"
          :field="item.field_name"
          :label="item.label.toLowerCase()"
          :hint="fileHint(item)"
          :accept="fileAccept(item)"
          :info="fileInfo[item.field_name]"
          :over="dragOver === item.field_name"
          :error="errors[item.field_name]"
          @change="pickFile(item, $event.target.files?.[0], $event.target)"
          @drop="dragOver = null; pickFile(item, $event.dataTransfer?.files?.[0])"
          @dragenter="dragOver = item.field_name"
          @dragover="dragOver = item.field_name"
          @dragleave="dragOver = null"
          @remove="removeFile(item)"
        />
        <div v-else class="af-field">
          <label class="af-label" :for="`field-${item.id}`">Corrected {{ item.label.toLowerCase() }}</label>
          <textarea
            v-if="item.type === 'textarea'"
            :id="`field-${item.id}`"
            v-model="values[item.field_name]"
            class="af-input rs-textarea"
            rows="5"
            :maxlength="item.max || undefined"
            :aria-invalid="!!errors[item.field_name]"
            @input="errors[item.field_name] = ''"
          ></textarea>
          <input
            v-else
            :id="`field-${item.id}`"
            v-model="values[item.field_name]"
            type="text"
            class="af-input"
            :maxlength="item.max || undefined"
            :aria-invalid="!!errors[item.field_name]"
            @input="errors[item.field_name] = ''"
          />
          <p class="af-note bad" aria-live="polite">{{ errors[item.field_name] }}</p>
        </div>
      </article>

      <!-- Already sent earlier, waiting for review -->
      <ul v-if="reviewingItems.length" class="rs-sent rs-sent--inline" aria-label="Already sent">
        <li v-for="item in reviewingItems" :key="item.id">
          <span>{{ item.label }}</span>
          <b>Sent · waiting for review</b>
        </li>
      </ul>

      <AuthNotice v-if="submitError" tone="warn" error :title="submitError.title" :body="submitError.body" />

      <button type="submit" class="af-btn" :class="{ busy: submitting }" :disabled="submitting || preparing">
        {{ submitLabel }}
      </button>
      <p class="af-note rs-fine">Only the items above are updated. The rest of your application stays as it is.</p>
    </form>
  </AuthShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute } from "vue-router";
import api from "../../plugins/axios";
import AuthShell from "../../components/auth/AuthShell.vue";
import AuthNotice from "../../components/auth/AuthNotice.vue";
import DocDrop from "../../components/vendor/register/DocDrop.vue";

// Phone photos are often 4–12 MB. Documents stay readable at this size, and
// the upload is much quicker on mobile data.
const MAX_SIDE = 2400;
const SHRINK_OVER = 1.5 * 1024 * 1024;

const route = useRoute();
const token = computed(() => route.params.token);

const loading = ref(true);
const loadError = ref("");
const vendorName = ref("");
const storeName = ref("");
const expiresAt = ref(null);
const items = ref([]);
const values = reactive({});
const files = reactive({});
const fileInfo = reactive({});
const errors = reactive({});
const dragOver = ref(null);
const preparing = ref(false);
const submitting = ref(false);
const progress = ref(0);
const submitError = ref(null);
const justSent = ref(false);

const pendingItems = computed(() => items.value.filter((i) => i.status === "pending_resubmission"));
const reviewingItems = computed(() => items.value.filter((i) => i.status === "resubmitted"));

const lead = computed(() => {
  if (loading.value || loadError.value || !pendingItems.value.length) return "";
  const first = (vendorName.value || "").split(" ")[0];
  const what = pendingItems.value.length === 1 ? "one item" : `${pendingItems.value.length} items`;
  return `${first ? `Hello ${first}. ` : ""}Our review team needs ${what} again${storeName.value ? ` for ${storeName.value}` : ""}. Everything else in your application is fine.`;
});

const fmtDate = (iso) =>
  new Date(iso).toLocaleDateString("en-PH", { month: "long", day: "numeric", year: "numeric" });
const expiresLabel = computed(() => (expiresAt.value ? fmtDate(expiresAt.value) : ""));

const submitLabel = computed(() => {
  if (preparing.value) return "Getting your photo ready…";
  if (!submitting.value) return pendingItems.value.length > 1 ? "Send updates" : "Send update";
  return progress.value > 0 && progress.value < 100 ? `Uploading… ${progress.value}%` : "Sending…";
});

const mimesOf = (item) => (item.mimes || "jpg,jpeg,png,pdf").split(",");
const fileAccept = (item) => mimesOf(item).map((m) => `.${m}`).join(",");
const sizeLabel = (kb) => `${Math.round(kb / 1024)} MB`;
function fileHint(item) {
  const types = mimesOf(item).filter((m) => m !== "jpeg").map((m) => m.toUpperCase());
  const last = types.pop();
  return `${types.length ? `${types.join(", ")} or ` : ""}${last}${item.max ? ` · up to ${sizeLabel(item.max)}` : ""}`;
}

async function load() {
  loading.value = true;
  loadError.value = "";
  try {
    const { data } = await api.get(`/vendor/resubmission/${token.value}`);
    vendorName.value = data.vendor_name || "";
    storeName.value = data.store_name || "";
    expiresAt.value = data.expires_at || null;
    items.value = data.items || [];
    for (const item of items.value) {
      if (item.type !== "file") values[item.field_name] = values[item.field_name] ?? item.resubmitted_value ?? "";
    }
  } catch (error) {
    loadError.value = error.response?.data?.message || error.message || "This link may have expired.";
  } finally {
    loading.value = false;
  }
}

// Large photos are redrawn smaller as JPEG; PDFs and small files go as they are
async function shrinkPhoto(file) {
  if (!/^image\/(jpeg|png)$/.test(file.type) || file.size <= SHRINK_OVER) return file;
  try {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, MAX_SIDE / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement("canvas");
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const ctx = canvas.getContext("2d");
    ctx.fillStyle = "#fff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close?.();
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.85));
    if (!blob || blob.size >= file.size) return file;
    return new File([blob], file.name.replace(/\.\w+$/, "") + ".jpg", { type: "image/jpeg" });
  } catch {
    return file;
  }
}

async function pickFile(item, picked, input) {
  dragOver.value = null;
  if (input) input.value = "";
  if (!picked) return;
  errors[item.field_name] = "";
  submitError.value = null;

  const ext = (picked.name.split(".").pop() || "").toLowerCase();
  if (!mimesOf(item).includes(ext)) {
    errors[item.field_name] = `Please choose a ${fileHint(item).split(" · ")[0]} file.`;
    return;
  }

  preparing.value = true;
  const file = await shrinkPhoto(picked);
  preparing.value = false;

  if (item.max && file.size > item.max * 1024) {
    errors[item.field_name] = `This file is ${(file.size / 1048576).toFixed(1)} MB. Please choose one up to ${sizeLabel(item.max)}, or a clear photo of the page.`;
    return;
  }

  if (fileInfo[item.field_name]?.url) URL.revokeObjectURL(fileInfo[item.field_name].url);
  files[item.field_name] = file;
  fileInfo[item.field_name] = {
    name: picked.name,
    size: file.size,
    type: file.type,
    url: file.type.startsWith("image/") ? URL.createObjectURL(file) : null,
  };
}

function removeFile(item) {
  if (fileInfo[item.field_name]?.url) URL.revokeObjectURL(fileInfo[item.field_name].url);
  delete files[item.field_name];
  delete fileInfo[item.field_name];
}

function validate() {
  let ok = true;
  for (const item of pendingItems.value) {
    const missing = item.type === "file" ? !files[item.field_name] : !String(values[item.field_name] || "").trim();
    if (missing) {
      errors[item.field_name] = item.type === "file" ? `Please add your ${item.label.toLowerCase()}.` : `Please fill in the ${item.label.toLowerCase()}.`;
      ok = false;
    }
  }
  return ok;
}

async function submit() {
  submitError.value = null;
  if (!validate()) {
    submitError.value = { title: "One more thing", body: "Please complete the highlighted item before sending." };
    return;
  }

  const form = new FormData();
  for (const item of pendingItems.value) {
    form.append(`fields[${item.field_name}]`, item.type === "file" ? files[item.field_name] : values[item.field_name].trim());
  }

  submitting.value = true;
  progress.value = 0;
  try {
    await api.post(`/vendor/resubmission/${token.value}`, form, {
      timeout: 180000,
      onUploadProgress: (e) => {
        if (e.total) progress.value = Math.round((e.loaded / e.total) * 100);
      },
    });
    justSent.value = true;
    await load();
    window.scrollTo({ top: 0, behavior: "smooth" });
  } catch (error) {
    const data = error.response?.data || {};
    // Field problems go next to the field
    if (data.errors) {
      for (const [key, list] of Object.entries(data.errors)) {
        errors[key.replace(/^fields\./, "")] = Array.isArray(list) ? list[0] : list;
      }
      submitError.value = { title: "Please check the highlighted item", body: "" };
    } else if (!error.response) {
      submitError.value = {
        title: "Your update didn't reach us",
        body: "The connection dropped while sending. Please check your internet and try again. Your file is still attached.",
      };
    } else {
      submitError.value = {
        title: "We couldn't send your update",
        body: data.message || error.message || "Please try again in a moment.",
      };
    }
  } finally {
    submitting.value = false;
  }
}

onMounted(load);
</script>

<style scoped>
.rs-list {
  display: grid;
  gap: 16px;
}

.rs-item {
  display: grid;
  gap: 14px;
  padding: 20px;
  border: 1px solid var(--line);
  border-radius: 18px;
  background: #fff;
}

.rs-item-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 8px 12px;
}

.rs-item-head h2 {
  margin: 0;
  font-family: var(--font-sans);
  font-size: 16px;
  font-weight: 500;
  color: var(--ink);
}

.rs-chip {
  padding: 4px 11px;
  border-radius: 999px;
  background: rgba(212, 178, 122, 0.2);
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.08em;
  color: #7a5b22;
  text-transform: uppercase;
  white-space: nowrap;
}

.rs-reason {
  padding: 12px 14px;
  border-left: 3px solid var(--gold);
  border-radius: 4px 12px 12px 4px;
  background: var(--ivory);
}

.rs-reason-lbl {
  display: block;
  margin-bottom: 2px;
  font-size: 11px;
  font-weight: 500;
  letter-spacing: 0.12em;
  color: var(--ink-soft);
  text-transform: uppercase;
}

.rs-reason p {
  margin: 0;
  font-size: 14.5px;
  line-height: 1.55;
  color: var(--ink);
  overflow-wrap: anywhere;
}

.rs-textarea {
  height: auto;
  padding: 12px 14px;
  line-height: 1.55;
  resize: vertical;
}

.rs-expiry {
  display: flex;
  align-items: center;
}

.rs-fine {
  text-align: center;
}

/* Sent */
.rs-done {
  display: grid;
  justify-items: center;
  gap: 12px;
  padding: 8px 0;
  text-align: center;
}

.rs-done-mark {
  display: grid;
  place-items: center;
  width: 58px;
  height: 58px;
  border-radius: 50%;
  background: rgba(77, 177, 115, 0.14);
  color: var(--leaf-deep);
}

.rs-done-mark svg {
  width: 28px;
  height: 28px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.rs-done h2 {
  margin: 4px 0 0;
  font-size: 28px;
  color: var(--ink);
}

.rs-done > p {
  max-width: 46ch;
  margin: 0;
  color: var(--ink-soft);
  font-size: 14.5px;
}

.rs-sent {
  display: grid;
  gap: 8px;
  width: 100%;
  margin: 8px 0 4px;
  padding: 0;
  list-style: none;
  text-align: left;
}

.rs-sent li {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
  gap: 4px 12px;
  padding: 12px 14px;
  border: 1px solid var(--line);
  border-radius: 12px;
  background: #fff;
  font-size: 14px;
  color: var(--ink);
}

.rs-sent b {
  font-weight: 500;
  color: var(--leaf-deep);
}

.rs-sent--inline {
  margin: 0;
}

.rs-home {
  margin-top: 6px;
  text-decoration: none;
}

/* Skeleton */
.rs-sk {
  height: 14px;
}

.rs-sk--title {
  width: 45%;
  height: 18px;
}

.rs-sk--line {
  width: 80%;
}

.rs-sk--drop {
  height: 68px;
  border-radius: 16px;
}

@media (max-width: 480px) {
  .rs-item {
    padding: 16px;
  }
}
</style>
