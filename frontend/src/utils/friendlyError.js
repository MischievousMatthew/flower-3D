// Plain-language errors for everyone who isn't a programmer. The server
// already swaps programmer errors for a message with a code (BC-XXXXXX);
// this covers what it never sees: no internet, timeouts, the server waking
// up, and anything technical that still slips through.

const TECHNICAL = [
  /SQLSTATE\[/,
  /\bSQL:/,
  /\(Connection: /,
  /\b[A-Z]\w*(Exception|Error)\b/,
  /\.php\b/,
  / on line \d+/,
  /Call to (undefined|a member function)/,
  /Undefined (variable|array key|offset|index|property|method)/,
  /must be of type /,
  /(column|relation|table) "?[\w.]+"? does not exist/i,
  /Integrity constraint/,
  /syntax error/i,
  /cURL error \d+/,
  /stack trace/i,
  /<\/?(html|body|head)\b/i,
  /^Request failed with status code \d+$/,
  /^Network Error$/,
  /^timeout of \d+ms exceeded$/,
];

export const MESSAGES = {
  offline: "Can't reach BloomCraft. Check your internet connection and try again.",
  timeout: "This is taking longer than usual. Please try again.",
  waking: "BloomCraft is starting up. Please try again in a few seconds.",
  load: "We couldn't load this right now. Please try again.",
  save: "We couldn't finish that. Please try again.",
};

/** Laravel's stock wording for ordinary refusals, reworded by status */
const FRAMEWORK = /^(Server Error|Unauthenticated\.|Unauthorized\.|This action is unauthorized\.|Too Many Attempts\.|Not Found|Forbidden|Method Not Allowed|Service Unavailable|Page Expired|CSRF token mismatch\.|The route .+ could not be found\.)$/;
const FOR_STATUS = {
  401: "Please sign in again to continue.",
  403: "You don't have permission to do that.",
  404: "We couldn't find that. It may have been moved or removed.",
  413: "That file is too large. Please choose a smaller one.",
  419: "This page has expired. Refresh the page and try again.",
  429: "Too many tries. Please wait a moment and try again.",
};

export function isTechnical(text) {
  return typeof text === "string" && TECHNICAL.some((pattern) => pattern.test(text));
}

/** The message a person should see for a failed request */
export function friendlyErrorText(error) {
  const method = (error?.config?.method ?? "get").toLowerCase();
  const generic = method === "get" ? MESSAGES.load : MESSAGES.save;
  const response = error?.response;

  if (!response) {
    if (error?.code === "ECONNABORTED" || error?.code === "ETIMEDOUT") return MESSAGES.timeout;
    if (error?.code === "ERR_CANCELED") return "";
    return MESSAGES.offline;
  }

  const data = response.data;
  const message = data && typeof data === "object" ? data.message : null;
  const stock = typeof message === "string" && FRAMEWORK.test(message.trim());
  if (typeof message === "string" && message.trim() && !stock && !isTechnical(message)) return message;
  if (FOR_STATUS[response.status]) return FOR_STATUS[response.status];

  // The host answers with its own page while the server restarts or wakes up
  if ([502, 503, 504].includes(response.status) && (typeof data !== "object" || data === null)) return MESSAGES.waking;

  const reference = data && typeof data === "object" ? data.reference : null;
  return reference ? `${generic} If it keeps happening, contact support and mention code ${reference}.` : generic;
}

/**
 * Rewrites a failed request in place, so every screen that shows
 * `error.message` or `error.response.data.message` shows plain words.
 */
export function makeFriendly(error) {
  const text = friendlyErrorText(error);
  if (!text) return error;
  error.message = text;
  const response = error?.response;
  if (response) {
    if (response.data && typeof response.data === "object") {
      if (response.data.message !== text) response.data.message = text;
      if (isTechnical(response.data.error)) delete response.data.error;
    } else {
      response.data = { success: false, message: text };
    }
  }
  return error;
}
