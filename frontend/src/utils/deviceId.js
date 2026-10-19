// A random id for this browser, so signing in again here is never mistaken
// for "another device". Not tied to the person; cleared with site data.
const KEY = "bc_device_id";

const randomId = () =>
  globalThis.crypto?.randomUUID?.() ||
  `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}-${Math.random().toString(36).slice(2)}`;

export function getDeviceId() {
  try {
    let id = localStorage.getItem(KEY);
    if (!id) {
      id = randomId();
      localStorage.setItem(KEY, id);
    }
    return id;
  } catch {
    // Storage blocked: the server then treats every sign-in as a new browser
    return null;
  }
}
