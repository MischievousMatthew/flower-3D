<template>
  <section class="eq">
    <div class="eq-preview">
      <div class="eq-badge">
        <div class="eq-brand">
          <img v-if="shop.logo && logoOk" :src="shop.logo" alt="" crossorigin="anonymous" @error="logoOk = false" />
          <span v-else-if="shop.name" class="eq-initials">{{ initialsOf(shop.name) }}</span>
          <b>{{ shop.name }}</b>
        </div>
        <span class="eq-kind">Attendance badge</span>
        <div class="eq-code">
          <canvas v-show="ready" ref="canvas" :aria-label="`Attendance QR code for ${employee_name}`"></canvas>
          <div v-if="!ready && !qrError" class="bc-skeleton eq-code-sk"></div>
          <p v-if="qrError" class="eq-code-error">{{ qrError }}</p>
        </div>
        <p class="eq-name">{{ employee_name }}</p>
        <p v-if="position || employee_code" class="eq-meta">
          <span v-if="position">{{ position }}</span>
          <span v-if="employee_code" class="eq-id">{{ employee_code }}</span>
        </p>
      </div>
    </div>

    <div class="eq-side">
      <h3>Attendance QR code</h3>
      <p>Print it on their ID badge. They scan it to clock in and out, and to open the shop's leave page.</p>
      <p v-if="ready && !logoUsed" class="sp-hint">{{ shop.logo ? "Your logo couldn't be loaded into the code, so your initials are used." : "Add a shop logo in Shop Profile to show it in the middle." }}</p>

      <div class="eq-actions">
        <button type="button" class="sp-btn sp-btn-primary" :disabled="!ready || !canExport" :title="canExport ? '' : permissionMessages.export" @click="downloadQR">
          <span v-html="ICONS.download"></span>Download badge (PNG)
        </button>
        <button type="button" class="sp-btn sp-btn-ghost" :disabled="!ready || !canPrint" :title="canPrint ? '' : permissionMessages.print" @click="printQR">
          <span v-html="ICONS.print"></span>Print
        </button>
      </div>

      <ol class="eq-steps">
        <li>Open the attendance scanner on the shop's approved device</li>
        <li>Hold this code up to its camera, then follow the face check</li>
        <li>First scan is time in, the next one is time out</li>
      </ol>

      <button v-if="canReissue" type="button" class="eq-reissue" :class="{ confirm: confirmReissue }" :disabled="reissuing" @click="reissue">
        <span v-html="ICONS.restore"></span>
        {{ reissuing ? "Making a new code…" : confirmReissue ? "Yes, make a new code. Old copies stop working" : "Lost or shared? Make a new code" }}
      </button>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, watch, computed, nextTick } from "vue";
import { toast } from "@/utils/snackbar";
import api from "../../plugins/axios";
import { useAssignment } from "../../composables/useAssignment";
import { SHOP_ICONS as ICONS } from "../../components/vendor/shop/shopIcons";
import { initialsOf } from "../../components/hr/hrUi";
import { drawQrWithLogo } from "../../components/hr/qrWithLogo";

const props = defineProps({
  employee_id: { type: [String, Number], required: true },
  owner_id: { type: [String, Number], required: true },
  employee_name: { type: String, required: true },
  // The code people know them by (EMP-0012) and their job, shown on the badge
  employee_code: { type: String, default: "" },
  position: { type: String, default: "" },
});

const SIZE = 1024;
const canvas = ref(null);
const ready = ref(false);
const qrError = ref("");
const shop = ref({ name: "", logo: null });
const logoOk = ref(true);
const logoUsed = ref(false);

const { can } = useAssignment();
const canExport = computed(() => can("employees", "export"));
const canPrint = computed(() => can("employees", "print"));
const canReissue = computed(() => can("employees", "edit"));
const permissionMessages = {
  export: "You don't have permission to export this data.",
  print: "You don't have permission to print this information.",
};

const reissuing = ref(false);
const confirmReissue = ref(false);

// The server signs the code, so it can't be made by hand
async function renderQRCode(signed = null) {
  qrError.value = "";
  ready.value = false;
  try {
    const data = signed ?? (await api.get(`/employees/${props.employee_id}/qr-code/payload`)).data.data;
    shop.value = data.shop || shop.value;
    logoOk.value = true;
    await nextTick();
    logoUsed.value = await drawQrWithLogo(canvas.value, data.qr_data, { logo: shop.value.logo, name: shop.value.name, size: SIZE });
    ready.value = true;
  } catch (error) {
    qrError.value = error?.response?.data?.message || "Couldn't load the QR code.";
  }
}

// A lost or shared code: make a new one; every older copy stops working
async function reissue() {
  if (!confirmReissue.value) {
    confirmReissue.value = true;
    return;
  }
  reissuing.value = true;
  try {
    const { data } = await api.post(`/employees/${props.employee_id}/qr-code/reissue`);
    await renderQRCode(data.data);
    toast.success(data.message);
  } catch (error) {
    toast.error(error?.response?.data?.message || "Couldn't make a new code.");
  } finally {
    reissuing.value = false;
    confirmReissue.value = false;
  }
}

/** The whole badge as one picture: shop name, the code, then the person's name and ID */
function badgeImage() {
  const W = 1200;
  const H = 1560;
  const c = document.createElement("canvas");
  c.width = W;
  c.height = H;
  const ctx = c.getContext("2d");
  ctx.fillStyle = "#ffffff";
  ctx.fillRect(0, 0, W, H);
  const accent = getComputedStyle(document.documentElement).getPropertyValue("--bc-accent").trim() || "#48bb78";
  ctx.fillStyle = accent;
  ctx.fillRect(0, 0, W, 18);

  ctx.textAlign = "center";
  ctx.fillStyle = "#374151";
  ctx.font = "600 44px Poppins, Arial, sans-serif";
  ctx.fillText(shop.value.name || "", W / 2, 110, W - 120);
  ctx.fillStyle = "#6b7280";
  ctx.font = "600 30px Poppins, Arial, sans-serif";
  ctx.fillText("ATTENDANCE BADGE", W / 2, 165);

  const q = 960;
  ctx.drawImage(canvas.value, (W - q) / 2, 200, q, q);

  ctx.fillStyle = "#111827";
  ctx.font = "700 64px Poppins, Arial, sans-serif";
  ctx.fillText(props.employee_name, W / 2, 1270, W - 120);
  ctx.fillStyle = "#4b5563";
  ctx.font = "500 40px Poppins, Arial, sans-serif";
  const meta = [props.position, props.employee_code].filter(Boolean).join("  ·  ");
  if (meta) ctx.fillText(meta, W / 2, 1340, W - 120);
  return c.toDataURL("image/png");
}

function downloadQR() {
  if (!canExport.value || !ready.value) return;
  const a = document.createElement("a");
  a.href = badgeImage();
  a.download = `badge-${(props.employee_code || props.employee_id)}-${props.employee_name.toLowerCase().replace(/[^a-z0-9]+/g, "-")}.png`;
  a.click();
}

function printQR() {
  if (!canPrint.value || !ready.value) return;
  const image = badgeImage();
  const name = props.employee_name.replace(/[<>&"]/g, "");
  const win = window.open("", "_blank", "width=700,height=900");
  if (!win) return;
  win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>Attendance badge — ${name}</title>
<style>@page{size:A4;margin:18mm}body{margin:0;display:flex;justify-content:center;font-family:Poppins,Arial,sans-serif}
img{width:86mm;border:1px solid #e5e7eb;border-radius:4mm}</style></head>
<body><img src="${image}" alt="Attendance badge"><script>window.onload=()=>{window.print()}<\/script></body></html>`);
  win.document.close();
}

onMounted(() => renderQRCode());

watch(
  () => [props.employee_id, props.owner_id],
  () => renderQRCode(),
);
</script>

<style scoped>
.eq {
  display: grid;
  gap: 18px;
}
.eq-preview {
  padding: 18px;
  border-radius: 18px;
  background: var(--v-subtle);
}
/* The badge is what gets printed, so it stays white in dark mode too */
.eq-badge {
  position: relative;
  max-width: 280px;
  margin: 0 auto;
  padding: 22px 18px 18px;
  overflow: hidden;
  border-radius: 16px;
  background: #ffffff;
  color: #111827;
  text-align: center;
  box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
}
.eq-badge::before {
  content: "";
  position: absolute;
  inset: 0 0 auto;
  height: 5px;
  background: var(--bc-accent);
}
.eq-brand {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  min-height: 28px;
}
.eq-brand img,
.eq-initials {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  object-fit: cover;
}
.eq-initials {
  display: grid;
  place-items: center;
  background: var(--bc-accent);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
}
.eq-brand b {
  font-size: 13px;
  color: #374151;
}
.eq-kind {
  display: block;
  margin: 6px 0 10px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: #6b7280;
}
.eq-code canvas {
  display: block;
  width: 100%;
  max-width: 220px;
  height: auto;
  aspect-ratio: 1;
  margin: 0 auto;
}
.eq-code-sk {
  width: 220px;
  max-width: 100%;
  aspect-ratio: 1;
  margin: 0 auto;
  border-radius: 12px;
}
.eq-code-error {
  display: grid;
  place-items: center;
  width: 220px;
  max-width: 100%;
  aspect-ratio: 1;
  margin: 0 auto;
  font-size: 13px;
  color: #b91c1c;
}
.eq-name {
  margin: 12px 0 2px;
  font-size: 17px;
  font-weight: 700;
  overflow-wrap: anywhere;
}
.eq-meta {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 6px;
  margin: 0;
  font-size: 12.5px;
  color: #4b5563;
}
.eq-id {
  padding: 1px 8px;
  border-radius: 999px;
  background: #f3f4f6;
  font-weight: 600;
  color: #374151;
}
.eq-side h3 {
  margin: 0 0 6px;
  font-size: 16px;
  font-weight: 600;
  color: var(--v-text);
}
.eq-side p {
  margin: 0 0 12px;
  font-size: 13.5px;
  color: var(--v-muted);
}
.eq-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 4px 0 14px;
}
.eq-steps {
  margin: 0 0 14px;
  padding: 12px 14px 12px 32px;
  border-radius: 12px;
  background: var(--v-subtle);
  font-size: 13px;
  line-height: 1.7;
  color: var(--v-text);
}
.eq-reissue {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  min-height: 44px;
  padding: 9px 12px;
  border: 1px dashed var(--v-border);
  border-radius: 10px;
  background: none;
  color: var(--v-muted);
  font: inherit;
  font-size: 13px;
  cursor: pointer;
  transition: border-color 0.15s, color 0.15s;
}
.eq-reissue :deep(svg) {
  width: 16px;
  height: 16px;
}
.eq-reissue:hover:not(:disabled),
.eq-reissue.confirm {
  border-color: #e53e3e;
  color: #e53e3e;
}
.eq-reissue:disabled {
  opacity: 0.6;
  cursor: wait;
}
</style>
