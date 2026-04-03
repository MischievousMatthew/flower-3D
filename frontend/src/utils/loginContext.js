const GEOLOCATION_TIMEOUT_MS = 4000;

function normalizePlatformName(rawPlatform, userAgent) {
  const source = `${rawPlatform ?? ""} ${userAgent ?? ""}`;
  if (!source.trim()) return "Unknown";
  if (/win/i.test(source)) return "Windows";
  if (/mac/i.test(source)) return "macOS";
  if (/android/i.test(source)) return "Android";
  if (/(iphone|ipad|ipod|ios)/i.test(source)) return "iOS";
  if (/linux/i.test(source)) return "Linux";
  return rawPlatform || "Unknown";
}

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
  return normalizePlatformName(fallbackPlatform, userAgent);
}

function detectDeviceType(userAgent, maxTouchPoints = 0, screenWidth = 0) {
  if (!userAgent) return "Unknown device";

  if (/(iphone|ipod|android.+mobile|windows phone)/i.test(userAgent)) {
    return "Phone";
  }

  if (/(ipad|tablet|android(?!.*mobile))/i.test(userAgent)) {
    return "Tablet";
  }

  if (maxTouchPoints > 1 && screenWidth > 0 && screenWidth <= 1024) {
    return "Tablet";
  }

  if (screenWidth > 0 && screenWidth <= 1440) {
    return "Laptop";
  }

  return "Desktop";
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
  const screenWidth = window.screen?.width ?? 0;
  const platform = detectPlatform(userAgent, window.navigator?.platform ?? "");
  const browser = detectBrowser(userAgent);
  const deviceType = detectDeviceType(
    userAgent,
    window.navigator?.maxTouchPoints ?? 0,
    screenWidth,
  );
  const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone ?? null;
  const position = await getCurrentPosition();

  const context = {
    device_name: deviceType,
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
