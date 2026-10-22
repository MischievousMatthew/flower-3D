// Browser side of the attendance face check: camera, face-api (landmarks, expressions,
// descriptors) and Human's anti-spoof + liveness models. Loaded only on the scanner page.
import * as faceapi from "@vladmandic/face-api";
import { eyeRatio, yaw } from "./attendanceMath";

const FACE_MODELS = "/models";
const HUMAN_MODELS = "/models/human/";
const VIRTUAL_CAMERA = /obs|manycam|xsplit|snap camera|virtual|fake|droidcam|iriun|epoccam|camo|mmhmm|vcam/i;

let faceReady = null;
let human = null;
let humanReady = null;

export function loadModels(onProgress = () => {}) {
  faceReady ??= (async () => {
    const nets = [faceapi.nets.tinyFaceDetector, faceapi.nets.faceLandmark68Net, faceapi.nets.faceExpressionNet, faceapi.nets.faceRecognitionNet];
    let done = 0;
    for (const net of nets) {
      await net.loadFromUri(FACE_MODELS);
      onProgress(++done / (nets.length + 1));
    }
  })();
  humanReady ??= (async () => {
    const { Human } = await import("@vladmandic/human");
    human = new Human({
      modelBasePath: HUMAN_MODELS,
      backend: "webgl",
      debug: false,
      cacheSensitivity: 0,
      filter: { enabled: false },
      gesture: { enabled: false },
      body: { enabled: false },
      hand: { enabled: false },
      object: { enabled: false },
      segmentation: { enabled: false },
      face: {
        enabled: true,
        detector: { modelPath: "blazeface.json", rotation: false, maxDetected: 1, skipFrames: 0, skipTime: 0, minConfidence: 0.3 },
        mesh: { enabled: false },
        iris: { enabled: false },
        attention: { enabled: false },
        description: { enabled: false },
        emotion: { enabled: false },
        antispoof: { enabled: true, modelPath: "antispoof.json", skipFrames: 0, skipTime: 0 },
        liveness: { enabled: true, modelPath: "liveness.json", skipFrames: 0, skipTime: 0 },
      },
    });
    await human.load();
  })();
  return Promise.all([faceReady, humanReady]).then(() => onProgress(1));
}

/** The real camera, never a virtual one or a screen share. Throws with a readable message. */
export async function startCamera(video) {
  if (!navigator.mediaDevices?.getUserMedia) throw new Error("This browser can't use the camera. Use Chrome or Edge.");
  let stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user", width: { ideal: 640 }, height: { ideal: 480 } }, audio: false });
  let track = stream.getVideoTracks()[0];

  if (VIRTUAL_CAMERA.test(track?.label ?? "")) {
    // Try another camera before giving up
    const devices = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === "videoinput" && !VIRTUAL_CAMERA.test(d.label));
    stream.getTracks().forEach((t) => t.stop());
    if (!devices.length) throw Object.assign(new Error("Only a virtual camera is available. Use the device's real camera."), { virtual: true });
    stream = await navigator.mediaDevices.getUserMedia({ video: { deviceId: { exact: devices[0].deviceId }, width: { ideal: 640 }, height: { ideal: 480 } }, audio: false });
    track = stream.getVideoTracks()[0];
  }
  const settings = track?.getSettings?.() ?? {};
  if (settings.displaySurface || VIRTUAL_CAMERA.test(track?.label ?? "")) {
    stream.getTracks().forEach((t) => t.stop());
    throw Object.assign(new Error("That camera isn't a real camera. Use the device's own camera."), { virtual: true });
  }

  video.srcObject = stream;
  await new Promise((resolve) => (video.readyState >= 2 ? resolve() : (video.onloadeddata = resolve)));
  await video.play().catch(() => {});

  return {
    stop: () => stream.getTracks().forEach((t) => t.stop()),
    camera: { label: track?.label ?? "", virtual: false, width: settings.width, height: settings.height },
  };
}

const detectorOptions = (size = 320) => new faceapi.TinyFaceDetectorOptions({ inputSize: size, scoreThreshold: 0.5 });

/**
 * One frame, normalised for the challenge judges.
 * Returns null when there's no face, or more than one face in view.
 */
export async function readFrame(video, { descriptor = false } = {}) {
  const all = await faceapi.detectAllFaces(video, detectorOptions()).withFaceLandmarks().withFaceExpressions();
  if (all.length !== 1) return all.length > 1 ? { many: true } : null;
  let det = all[0];
  if (descriptor) {
    const withDesc = await faceapi.detectSingleFace(video, detectorOptions(416)).withFaceLandmarks().withFaceDescriptor();
    if (!withDesc) return null;
    det = { ...det, descriptor: withDesc.descriptor };
  }
  const box = det.detection.box;
  const lm = det.landmarks;
  const leftEye = lm.getLeftEye();
  const rightEye = lm.getRightEye();
  const nose = lm.getNose()[3];

  return {
    box: { x: box.x, y: box.y, width: box.width, height: box.height },
    width: box.width,
    score: det.detection.score,
    ratio: (eyeRatio(leftEye) + eyeRatio(rightEye)) / 2,
    yaw: yaw(leftEye, rightEye, nose),
    noseY: (nose.y - box.y) / box.height,
    smile: det.expressions?.happy ?? 0,
    descriptor: det.descriptor ? Array.from(det.descriptor) : null,
    videoWidth: video.videoWidth,
    videoHeight: video.videoHeight,
  };
}

/** Average color of the middle of the face (cheeks, nose) */
export function faceColor(video, box) {
  const canvas = document.createElement("canvas");
  canvas.width = canvas.height = 24;
  const ctx = canvas.getContext("2d", { willReadFrequently: true });
  const inset = 0.25;
  ctx.drawImage(video, box.x + box.width * inset, box.y + box.height * inset, box.width * (1 - 2 * inset), box.height * (1 - 2 * inset), 0, 0, 24, 24);
  const px = ctx.getImageData(0, 0, 24, 24).data;
  const sum = [0, 0, 0];
  for (let i = 0; i < px.length; i += 4) {
    sum[0] += px[i];
    sum[1] += px[i + 1];
    sum[2] += px[i + 2];
  }
  const n = px.length / 4;
  return sum.map((v) => v / n);
}

/** A small JPEG of the camera image, kept as evidence */
export function snapshot(video, width = 320) {
  const canvas = document.createElement("canvas");
  canvas.width = width;
  canvas.height = Math.round((video.videoHeight / video.videoWidth) * width) || 240;
  canvas.getContext("2d").drawImage(video, 0, 0, canvas.width, canvas.height);
  return canvas.toDataURL("image/jpeg", 0.72);
}

/** Anti-spoof ("real") and liveness scores, averaged over a few frames */
export async function spoofScores(video, frames = 5) {
  await humanReady;
  const real = [];
  const live = [];
  for (let i = 0; i < frames; i++) {
    const result = await human.detect(video);
    const face = result.face?.[0];
    if (face) {
      real.push(face.real ?? 0);
      live.push(face.live ?? 0);
    }
    await new Promise((r) => setTimeout(r, 90));
  }
  const avg = (list) => (list.length ? list.reduce((a, b) => a + b, 0) / list.length : 0);
  return { antispoof: Math.round(avg(real) * 1000) / 1000, liveness: Math.round(avg(live) * 1000) / 1000 };
}

/** Descriptor of a profile photo, for people whose face isn't enrolled yet */
export async function photoDescriptor(url) {
  const img = await faceapi.fetchImage(url);
  const det = await faceapi.detectSingleFace(img, detectorOptions(416)).withFaceLandmarks().withFaceDescriptor();
  if (!det) throw new Error("No face was found in this person's profile photo.");
  return Array.from(det.descriptor);
}

export const wait = (ms) => new Promise((r) => setTimeout(r, ms));
