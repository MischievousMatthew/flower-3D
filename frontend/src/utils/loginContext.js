const GEOLOCATION_TIMEOUT_MS = 4000;

function detectBrowser(userAgent) {
  if (!userAgent) return "Unknown";
  if (userAgent.includes("Edg/")) return "Edge";
  if (userAgent.includes("OPR/") || userAgent.includes("Opera")) return "Opera";
  if (userAgent.includes("Chrome/")) return "Chrome";
  if (userAgent.includes("Safari/") && !userAgent.includes("Chrome/")) return "Safari";
  if (userAgent.includes("Firefox/")) return "Firefox";
  return "Unknown";
}

function detectPlatform(userAgent, fallbackPlatform) {
  if (fallbackPlatform) return fallbackPlatform;
  if (!userAgent) return "Unknown";
  if (userAgent.includes("Windows")) return "Windows";
  if (userAgent.includes("Mac OS")) return "macOS";
  if (userAgent.includes("Android")) return "Android";
  if (userAgent.includes("iPhone") || userAgent.includes("iPad")) return "iOS";
  if (userAgent.includes("Linux")) return "Linux";
  return "Unknown";
}

function getCurrentPosition() {
  return new Promise((resolve) => {
    if (!navigator.geolocation) {
      resolve(null);
      return;
    }

    navigator.geolocation.getCurrentPosition(
      (position) => resolve(position),
      () => resolve(null),
      {
        enableHighAccuracy: false,
        timeout: GEOLOCATION_TIMEOUT_MS,
        maximumAge: 5 * 60 * 1000,
      },
    );
  });
}

export async function buildLoginContext() {
  if (typeof window === "undefined") {
    return {};
  }

  const userAgent = window.navigator?.userAgent ?? "";
  const platform = detectPlatform(userAgent, window.navigator?.platform ?? "");
  const browser = detectBrowser(userAgent);
  const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone ?? null;
  const position = await getCurrentPosition();

  const context = {
    device_name: `${browser} on ${platform}`,
    browser,
    platform,
    timezone,
  };

  if (position?.coords) {
    const { latitude, longitude, accuracy } = position.coords;
    context.latitude = latitude;
    context.longitude = longitude;
    context.location_accuracy = Number.isFinite(accuracy)
      ? Math.round(accuracy)
      : null;
    context.location_label = `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;
  }

  return context;
}
