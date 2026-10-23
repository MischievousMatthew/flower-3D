// Sign-in problems: what went wrong with the request, and how the login page
// explains it — a short calm note with what to do next and, when waiting
// is the fix, a countdown. No vague "Login failed".

/** What went wrong with a sign-in request (an axios error) */
export function loginProblem(err) {
  const res = err?.response;
  if (!res) {
    const offline = typeof navigator !== "undefined" && navigator.onLine === false;
    return { status: 0, code: offline ? "offline" : "unreachable" };
  }
  const d = res.data ?? {};
  return {
    status: res.status,
    code: d.code ?? (res.status === 429 ? "too_many_requests" : res.status >= 500 ? "unreachable" : null),
    attemptsLeft: d.attempts_left ?? null,
    retryAfter: d.retry_after ?? (Number(res.headers?.["retry-after"]) || null),
  };
}

/**
 * The note for a failed sign-in. Kept to a sentence or two so it fits the
 * login form's fixed message space.
 * tone: "info" (neutral), "warn" (amber), "lock" (waiting), "offline"
 * field: which input to point at, if any
 * actions: ["forgot", "retry", "support"]
 */
export function explainLoginProblem(result = {}) {
  const left = result.attemptsLeft;
  const counted = left !== null && left !== undefined && left <= 3;
  switch (result.code) {
    case "invalid_credentials":
      return {
        tone: counted && left <= 2 ? "warn" : "info",
        title: "That didn't match",
        body: counted ? `Check your username and password. ${left} ${left === 1 ? "try" : "tries"} left.` : "Check your username and password.",
        field: "password",
        actions: ["forgot"],
      };
    case "account_locked":
      return { tone: "lock", title: "Sign-in paused", body: "Too many wrong passwords.", countdown: result.retryAfter || null, actions: ["forgot"] };
    case "account_suspended":
      return { tone: "warn", title: "Account on hold", body: result.error || "Your account has been suspended.", actions: ["support"] };
    case "account_inactive":
      return { tone: "info", title: "Staff sign-in is off", body: result.error || "Ask the shop owner to turn your account back on." };
    case "account_unverified":
      return { tone: "info", title: "Verify your email first", body: "Open the link we emailed you, then sign in.", actions: ["support"] };
    case "offline":
      return { tone: "offline", title: "You're offline", body: "Reconnect, then try again.", actions: ["retry"] };
    case "unreachable":
      return { tone: "offline", title: "BloomCraft didn't answer", body: "The server may be waking up.", actions: ["retry"] };
    case "too_many_requests":
      return { tone: "lock", title: "Slow down a little", body: "Too many tries in a short time.", countdown: result.retryAfter || 60 };
    default:
      return { tone: "info", title: "We couldn't sign you in", body: result.error || "Please try again.", actions: ["retry"] };
  }
}

/** "4:05" / "45s" */
export function formatWait(seconds) {
  const s = Math.max(0, Math.ceil(seconds));
  if (s < 60) return `${s}s`;
  return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, "0")}`;
}
