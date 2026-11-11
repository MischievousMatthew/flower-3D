// The password rules the server enforces: a minimum length (8, or 10 for
// administrators), an uppercase letter, a lowercase letter and a number
export const passwordRules = (pw = "", min = 8) => [
  { label: `${min} or more characters`, met: pw.length >= min },
  { label: "An uppercase letter", met: /[A-Z]/.test(pw) },
  { label: "A lowercase letter", met: /[a-z]/.test(pw) },
  { label: "A number", met: /\d/.test(pw) },
];
export const passwordOk = (pw, min = 8) => passwordRules(pw, min).every((r) => r.met);
/** How much of the list is done, 0–100 */
export const passwordPercent = (pw, min = 8) => {
  const rules = passwordRules(pw, min);
  return Math.round((rules.filter((r) => r.met).length / rules.length) * 100);
};

// Letters and numbers that can't be mistaken for each other (no 0/O, 1/l/I),
// plus a few symbols that are easy to type on a phone
const UPPER = "ABCDEFGHJKLMNPQRSTUVWXYZ";
const LOWER = "abcdefghijkmnopqrstuvwxyz";
const DIGITS = "23456789";
const SYMBOLS = "!@#$%*-_?";

/** A random index below `n`, from the browser's secure random source */
function randomBelow(n) {
  const cryptoApi = globalThis.crypto;
  const limit = Math.floor(0x100000000 / n) * n; // no bias toward the low numbers
  const buf = new Uint32Array(1);
  do cryptoApi.getRandomValues(buf);
  while (buf[0] >= limit);
  return buf[0] % n;
}

/**
 * A strong password that always meets the rules above: at least one
 * uppercase letter, lowercase letter, number and symbol, `length` long
 * (never shorter than the minimum), in random order.
 */
export function generatePassword(length = 16, min = 8) {
  const size = Math.max(length, min, 4);
  const pick = (set) => set[randomBelow(set.length)];
  const all = UPPER + LOWER + DIGITS + SYMBOLS;
  const chars = [pick(UPPER), pick(LOWER), pick(DIGITS), pick(SYMBOLS)];
  while (chars.length < size) chars.push(pick(all));
  for (let i = chars.length - 1; i > 0; i--) {
    const j = randomBelow(i + 1);
    [chars[i], chars[j]] = [chars[j], chars[i]];
  }
  return chars.join("");
}
