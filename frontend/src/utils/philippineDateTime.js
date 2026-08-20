export const PHILIPPINES_TIME_ZONE = "Asia/Manila";

const dateOnlyPattern = /^\d{4}-\d{2}-\d{2}$/;

function parseDateTime(value) {
  if (!value) return null;
  const date = value instanceof Date ? value : new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

export function formatPhilippineDate(value, options = {}) {
  if (!value) return "—";

  // Calendar dates are not instants. Anchor the calendar fields at noon only
  // to obtain their weekday/name; this avoids converting them through the
  // viewer's browser timezone.
  const date = dateOnlyPattern.test(String(value))
    ? (() => {
        const [year, month, day] = String(value).split("-").map(Number);
        return new Date(Date.UTC(year, month - 1, day, 12));
      })()
    : parseDateTime(value);

  if (!date || Number.isNaN(date.getTime())) return "—";
  return new Intl.DateTimeFormat("en-US", {
    timeZone: dateOnlyPattern.test(String(value)) ? "UTC" : PHILIPPINES_TIME_ZONE,
    year: "numeric",
    month: "long",
    day: "numeric",
    ...options,
  }).format(date);
}

export function formatPhilippineTime(value, options = {}) {
  const date = parseDateTime(value);
  if (!date) return "—";
  return new Intl.DateTimeFormat("en-US", {
    timeZone: PHILIPPINES_TIME_ZONE,
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
    ...options,
  }).format(date);
}

export function formatPhilippineDateTime(value) {
  return `${formatPhilippineDate(value)} • ${formatPhilippineTime(value)}`;
}

export function configurePhilippineDateTimeDefaults() {
  const methods = ["toLocaleDateString", "toLocaleTimeString", "toLocaleString"];

  methods.forEach((method) => {
    const nativeMethod = Date.prototype[method];
    Date.prototype[method] = function (locales, options) {
      return nativeMethod.call(this, locales, {
        ...(options || {}),
        timeZone: PHILIPPINES_TIME_ZONE,
      });
    };
  });
}
