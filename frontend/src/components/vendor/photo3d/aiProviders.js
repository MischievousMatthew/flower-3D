// Free photo-to-3D through public Hugging Face demos, called straight from the
// browser so the work never touches our server.
//
// Spaces break or move without notice: to swap one, edit PROVIDERS only.
// Two kinds ("pool"):
//   zerogpu: Hugging Face's shared free GPUs. Best quality, but the free quota
//            belongs to the visitor's connection and is shared by every ZeroGPU
//            Space (about one conversion a day), so once it runs out we skip
//            them all until it resets.
//   cpu:     free CPU Spaces. No quota, slower, smoother results.

const MINUTE = 60 * 1000;
const RUN_TIMEOUT = 4 * MINUTE;
const QUOTA_KEY = "bc_photo3d_gpu_quota_until";

/** Sorts an AI failure into what the page should tell the vendor */
export function classifyAiError(message = "") {
  const text = String(message);
  if (/exceeded your (zerogpu|gpu) quota|quota exceeded/i.test(text)) {
    const m = text.match(/try again in (\d+):(\d{2}):(\d{2})/i);
    const retryInSeconds = m ? Number(m[1]) * 3600 + Number(m[2]) * 60 + Number(m[3]) : null;
    return { code: "quota", retryInSeconds };
  }
  if (/queue is full|too many requests|\b429\b|busy/i.test(text)) return { code: "busy" };
  if (/no (object|subject) found|empty image|invalid image/i.test(text)) return { code: "bad-photo" };
  return { code: "down" };
}

class AiError extends Error {
  constructor(message, details) {
    super(message);
    Object.assign(this, details);
  }
}

function withTimeout(promise, ms) {
  let timer;
  const timeout = new Promise((_, reject) => {
    timer = setTimeout(() => reject(new Error("The free AI took too long")), ms);
  });
  return Promise.race([promise, timeout]).finally(() => clearTimeout(timer));
}

const loadClient = () => import("@gradio/client");

async function fetchFile(file) {
  const url = file?.url;
  if (!url) throw new Error("The AI didn't return a 3D file");
  const res = await fetch(url);
  if (!res.ok) throw new Error(`Couldn't download the 3D file (${res.status})`);
  const blob = await res.blob();
  return new Blob([blob], { type: "model/gltf-binary" });
}

export const PROVIDERS = [
  {
    id: "trellis",
    label: "TRELLIS",
    pool: "zerogpu",
    async run(photo, onStage) {
      const { Client, handle_file } = await loadClient();
      onStage?.("connect");
      const client = await Client.connect("trellis-community/TRELLIS");
      await client.predict("/start_session", {});
      onStage?.("background");
      const pre = await client.predict("/preprocess_image", { image: handle_file(photo) });
      onStage?.("shape");
      const out = await client.predict("/generate_and_extract_glb", {
        image: pre.data[0],
        multiimages: [],
        seed: 0,
        ss_guidance_strength: 7.5,
        ss_sampling_steps: 12,
        slat_guidance_strength: 3,
        slat_sampling_steps: 12,
        multiimage_algo: "stochastic",
        mesh_simplify: 0.95,
        texture_size: 1024,
      });
      // Returns [preview video, 3D model, download]; the download is the GLB
      onStage?.("download");
      return fetchFile(out.data[2] || out.data[1]);
    },
  },
  {
    // TripoSR on a free CPU Space: unlimited, about 1–2 minutes, vertex colors
    id: "triposr",
    label: "TripoSR",
    pool: "cpu",
    async run(photo, onStage) {
      const { Client, handle_file } = await loadClient();
      onStage?.("connect");
      const client = await Client.connect("WeReCooking2/Dust3R-TripoSR");
      onStage?.("background");
      const pre = await client.predict("/preprocess", { input_image: handle_file(photo), do_remove_background: true, foreground_ratio: 0.85 });
      onStage?.("shape");
      const out = await client.predict("/generate", { image: pre.data[0], mc_resolution: 256 });
      onStage?.("download");
      return fetchFile([].concat(out.data).find((f) => f?.url));
    },
  },
  {
    id: "hunyuan",
    label: "Hunyuan3D-2",
    pool: "zerogpu",
    async run(photo, onStage) {
      const { Client, handle_file } = await loadClient();
      onStage?.("connect");
      const client = await Client.connect("tencent/Hunyuan3D-2");
      onStage?.("shape");
      const out = await client.predict("/generation_all", {
        caption: null,
        image: handle_file(photo),
        mv_image_front: null,
        mv_image_back: null,
        mv_image_left: null,
        mv_image_right: null,
        steps: 30,
        guidance_scale: 5,
        seed: 1234,
        octree_resolution: 256,
        check_box_rembg: true,
        num_chunks: 8000,
        randomize_seed: true,
      });
      // Returns [white mesh, textured mesh, …]; prefer the textured one
      onStage?.("download");
      return fetchFile(out.data[1] || out.data[0]);
    },
  },
];

const localStore = () => {
  try {
    return window.localStorage;
  } catch (_) {
    return null;
  }
};

/** When the free GPU quota resets (ms timestamp), if a run said it's used up */
export function gpuQuotaUntil(storage = localStore(), now = Date.now) {
  try {
    const until = Number(storage?.getItem(QUOTA_KEY) || 0);
    return until > now() ? until : 0;
  } catch (_) {
    return 0;
  }
}

function rememberQuota(storage, until) {
  try {
    storage?.setItem(QUOTA_KEY, String(until));
  } catch (_) {
    // Storage blocked: we just ask the GPU again next time
  }
}

/**
 * Tries each provider in order. Resolves { blob, provider }; rejects with an
 * error carrying `code` ("quota" | "busy" | "down" | "bad-photo") and, for
 * quota, `retryInSeconds`.
 *
 * A quota error skips the remaining GPU providers (the quota is shared) but
 * still tries the CPU ones, and is remembered so later runs skip the GPU until
 * it resets. `onSkip(provider, untilMs)` reports a skipped GPU provider.
 */
export async function convertWithAI(photo, { providers = PROVIDERS, onStage, onProvider, onSkip, storage = localStore(), now = Date.now } = {}) {
  let last = null;
  let quota = null;
  for (const provider of providers) {
    if (provider.pool === "zerogpu") {
      const until = gpuQuotaUntil(storage, now);
      if (until) {
        quota ??= new AiError("The free GPU quota is used up", { code: "quota", retryInSeconds: Math.round((until - now()) / 1000), provider: provider.id });
        onSkip?.(provider, until);
        continue;
      }
    }
    onProvider?.(provider);
    try {
      const blob = await withTimeout(provider.run(photo, onStage), RUN_TIMEOUT);
      return { blob, provider };
    } catch (err) {
      const message = err?.message || String(err);
      const details = classifyAiError(message);
      last = new AiError(message, { ...details, provider: provider.id });
      if (details.code === "bad-photo") throw last;
      if (details.code === "quota") {
        quota = last;
        // No reset time given: assume an hour so we don't keep asking
        rememberQuota(storage, now() + (details.retryInSeconds ?? 3600) * 1000);
      }
    }
  }
  // Nothing worked: the quota is the most useful thing to tell the vendor
  throw quota || last || new AiError("No AI service is set up", { code: "down" });
}
