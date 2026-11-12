/**
 * Product pages show a scrambled code in the address instead of the product's
 * number (/…/1 becomes /…/kQ3mX9a2), so links stay shareable without showing
 * record IDs. Like the opaque route tokens, this only hides the number; the
 * API still decides what a visitor may see.
 */
const ALPHABET = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";
const KEYS = [0x5bd1, 0x9e37, 0x2c6b, 0xd2a7];
const LENGTH = 6; // 62^6 holds any 32-bit number

// A small Feistel shuffle over 32 bits: reversible, and neighbouring ids look unrelated
const round = (half, key) => (Math.imul(half ^ key, 0x45d9f3b) >>> 7) & 0xffff;
function scramble(n, decode = false) {
  let left = (n >>> 16) & 0xffff;
  let right = n & 0xffff;
  const keys = decode ? [...KEYS].reverse() : KEYS;
  if (decode) [left, right] = [right, left];
  for (const key of keys) [left, right] = [right, left ^ round(right, key)];
  if (decode) [left, right] = [right, left];
  return ((left << 16) | right) >>> 0;
}

function toBase62(n) {
  let out = "";
  for (let i = 0; i < LENGTH; i++) {
    out = ALPHABET[n % 62] + out;
    n = Math.floor(n / 62);
  }
  return out;
}

const checkChar = (body) => ALPHABET[[...body].reduce((sum, c, i) => sum + ALPHABET.indexOf(c) * (i + 3), 7) % 62];

/** 1 → "x4Gk2Lq" */
export function productCode(id) {
  const n = Number(id);
  if (!Number.isInteger(n) || n <= 0 || n > 0xffffffff) return String(id);
  const body = toBase62(scramble(n));
  return body + checkChar(body);
}

/** "x4Gk2Lq" → 1; a plain id like "1", or anything else → null (ids never open a page) */
export function productIdFromCode(code) {
  const value = String(code ?? "");
  if (value.length !== LENGTH + 1) return null;
  const body = value.slice(0, LENGTH);
  if (checkChar(body) !== value[LENGTH] || [...body].some((c) => !ALPHABET.includes(c))) return null;
  const n = [...body].reduce((acc, c) => acc * 62 + ALPHABET.indexOf(c), 0);
  if (n > 0xffffffff) return null;
  const id = scramble(n, true);
  return id > 0 ? id : null;
}

/** The router location for a product's page */
export const productRoute = (id) => ({ name: "ProductDetails", params: { id: productCode(id) } });
