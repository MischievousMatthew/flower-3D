// The shop scanner's connection to the server. The device token proves this browser is a
// device the shop owner approved; it stays in this browser only.
import api from "../plugins/axios";

const TOKEN_KEY = "bc_attendance_device";

export const deviceToken = {
  get() {
    try {
      return localStorage.getItem(TOKEN_KEY);
    } catch (_) {
      return null;
    }
  },
  set(token) {
    try {
      localStorage.setItem(TOKEN_KEY, token);
    } catch (_) {
      // Without storage the device can't be remembered; the request still reaches the owner
    }
  },
  clear() {
    try {
      localStorage.removeItem(TOKEN_KEY);
    } catch (_) {
      // nothing to clear
    }
  },
};

/** The id of the device this browser was set up as (the token starts with it) */
export function thisDeviceId() {
  const id = Number(String(deviceToken.get() ?? "").split(".")[0]);
  return Number.isInteger(id) && id > 0 ? id : null;
}

function readPosition(options) {
  return new Promise((resolve, reject) => {
    navigator.geolocation.getCurrentPosition(
      (p) => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: Math.round(p.coords.accuracy) }),
      reject,
      options,
    );
  });
}

/**
 * A fresh location reading. Asks for GPS first; computers without GPS often
 * can't give a precise fix in time, so it then settles for Wi-Fi location.
 * Rejects with a message people can act on; `blocked` is set when the browser
 * refuses, so pages can show how to allow it.
 */
export async function currentPosition() {
  if (!navigator.geolocation) {
    throw new Error("This browser can't share its location, so it can't take attendance.");
  }
  try {
    return await readPosition({ enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
  } catch (first) {
    if (first?.code !== 1) {
      try {
        return await readPosition({ enableHighAccuracy: false, timeout: 20000, maximumAge: 60000 });
      } catch {
        // fall through to the message below
      }
    }
    const error = new Error(
      first?.code === 1
        ? "Location is blocked for this site."
        : "Couldn't get your location. Turn on location (GPS or Wi-Fi) on this device and try again.",
    );
    error.blocked = first?.code === 1;
    throw error;
  }
}

/** How to allow location again, for the browser this is running in */
export function unblockLocationSteps() {
  const ua = navigator.userAgent;
  if (/iPhone|iPad|iPod/.test(ua)) {
    return ["Open Settings → Privacy & Security → Location Services and turn it on.", "Scroll to your browser (Safari or Chrome) and choose “While Using the App”.", "Come back here and try again."];
  }
  if (/Android/.test(ua)) {
    return ["Tap the icon to the left of the web address.", "Tap Permissions → Location → Allow.", "Make sure Location is on in your phone's quick settings, then try again."];
  }
  return [
    "Click the icon to the left of the web address (the sliders or lock).",
    "Turn Location on, or choose Site settings → Location → Allow.",
    /Windows/.test(ua)
      ? "Check Windows Settings → Privacy & security → Location is on, then reload this page."
      : /Mac OS X/.test(ua)
        ? "Check System Settings → Privacy & Security → Location Services allows your browser, then reload this page."
        : "Reload this page and try again.",
  ];
}

export const kioskMessage = (e, fallback) => {
  const errors = e?.response?.data?.errors;
  const first = errors ? Object.values(errors).flat()[0] : null;
  return first || e?.response?.data?.message || e?.message || fallback;
};

export const attendanceKiosk = {
  async status() {
    const { data } = await api.post("/attendance/kiosk/status", { device_token: deviceToken.get() ?? "" });
    return data.data;
  },
  async requestDevice(name, position) {
    const { data } = await api.post("/attendance/kiosk/devices", { name, ...position });
    deviceToken.set(data.data.device_token);
    return data;
  },
  async start(qrData, position, enroll = false) {
    const { data } = await api.post("/attendance/kiosk/sessions", { device_token: deviceToken.get(), qr_data: qrData, enroll, ...position });
    return data.data;
  },
  async complete(sessionId, payload, position) {
    const { data } = await api.post(`/attendance/kiosk/sessions/${sessionId}/complete`, { device_token: deviceToken.get(), ...payload, ...position });
    return data.data;
  },
  async enroll(sessionId, payload, position) {
    const { data } = await api.post(`/attendance/kiosk/sessions/${sessionId}/enroll`, { device_token: deviceToken.get(), ...payload, ...position });
    return data;
  },
};

export default attendanceKiosk;
