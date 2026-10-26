// Every contact number is a Philippine mobile number shown and saved as "09XX XXX XXXX"
// (the server does the same: App\Support\Phone).

export const PHONE_PLACEHOLDER = "09XX XXX XXXX";
export const PHONE_MESSAGE = "Enter a mobile number like 0917 123 4567.";

/** Digits as typed so far, with +63 / 63 / a leading 9 turned into 09… */
function phoneDigits(value) {
  const d = String(value ?? "").replace(/\D/g, "");
  if (d.startsWith("63") && d.length > 2) return `0${d.slice(2)}`;
  return d.startsWith("9") ? `0${d}` : d;
}

/** "0917123" → "0917 123", while someone is still typing (at most 11 digits) */
export function typePhone(value) {
  const d = phoneDigits(value).slice(0, 11);
  return [d.slice(0, 4), d.slice(4, 7), d.slice(7, 11)].filter(Boolean).join(" ");
}

/** A complete mobile number in "09XX XXX XXXX", or the value unchanged when it isn't one */
export function formatPhone(value) {
  const next = typePhone(value);
  return isPhone(next) && phoneDigits(value).length === 11 ? next : value;
}

export const isPhone = (value) => /^09\d{2} \d{3} \d{4}$/.test(String(value ?? ""));

/** Put the caret back after the same number of digits it was after */
function caretAfterDigits(text, digits) {
  if (digits <= 0) return 0;
  let seen = 0;
  for (let i = 0; i < text.length; i += 1) {
    if (/\d/.test(text[i]) && ++seen === digits) return i + 1;
  }
  return text.length;
}

function tidy(el) {
  if (!el.value || document.activeElement === el) return;
  const next = formatPhone(el.value);
  if (next !== el.value) {
    el.value = next;
    el.dispatchEvent(new Event("input"));
  }
}

/** v-phone: formats a phone input as "09XX XXX XXXX" while typing (works with v-model) */
export const vPhone = {
  mounted(el) {
    el.setAttribute("inputmode", "tel");
    el.setAttribute("maxlength", "13");
    el.setAttribute("placeholder", PHONE_PLACEHOLDER);
    if (!el.getAttribute("autocomplete")) el.setAttribute("autocomplete", "tel");
    el._phoneInput = () => {
      const next = typePhone(el.value);
      if (next === el.value) return;
      const digitsBefore = el.value.slice(0, el.selectionStart ?? el.value.length).replace(/\D/g, "").length;
      // 63 9… becomes 09… (one digit fewer), 9… becomes 09… (one more)
      const raw = el.value.replace(/\D/g, "");
      const shift = raw.startsWith("63") && raw.length > 2 ? -1 : raw.startsWith("9") ? 1 : 0;
      el.value = next;
      const at = caretAfterDigits(next, Math.max(0, digitsBefore + shift));
      if (document.activeElement === el) el.setSelectionRange(at, at);
      el.dispatchEvent(new Event("input"));
    };
    el.addEventListener("input", el._phoneInput);
    tidy(el);
  },
  updated: tidy,
  unmounted(el) {
    el.removeEventListener("input", el._phoneInput);
  },
};
