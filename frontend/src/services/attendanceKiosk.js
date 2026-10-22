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

/** A fresh, precise GPS reading. Rejects with a message people can act on. */
export function currentPosition() {
  return new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
      reject(new Error("This browser can't share its location, so it can't take attendance."));
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (p) => resolve({ latitude: p.coords.latitude, longitude: p.coords.longitude, accuracy: Math.round(p.coords.accuracy) }),
      (e) =>
        reject(new Error(
          e.code === 1
            ? "Location is blocked. Allow location for this site in the browser settings, then try again."
            : "Couldn't get your location. Turn on location (GPS) and try again.",
        )),
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
    );
  });
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
