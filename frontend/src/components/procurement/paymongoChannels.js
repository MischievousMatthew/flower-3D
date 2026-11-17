// Banks a funding request is paid through: only those PayMongo Checkout
// offers. Supplies cost too much for e-wallet limits, so it's banks only.
// Matches App\Support\PayMongoChannels on the server.
export const CHANNELS = {
  // QR Ph: a QR code the payer scans with their bank app
  bank: ["QR Ph", "BPI", "UnionBank", "BDO", "Landbank", "Metrobank"],
};

// No longer offered; only so older requests and supplier accounts still read right
export const OLD_EWALLETS = ["GCash", "Maya", "GrabPay", "ShopeePay"];

// Accounts a supplier can be paid into (a QR code can't receive money)
export const PAYOUT_CHANNELS = { bank: CHANNELS.bank.filter((c) => c !== "QR Ph") };

export const kindOf = (channel) => (OLD_EWALLETS.includes(channel) ? "ewallet" : "bank");

// PayMongo's smallest e-wallet and online-banking payment
export const PAYMONGO_MINIMUM = 20;

/**
 * How each account number looks, so it's typed the way the bank prints it:
 * how many digits, and how they're grouped. E-wallets use the mobile number.
 * Matches App\Support\PayMongoChannels::ACCOUNT_FORMATS.
 */
export const ACCOUNT_FORMATS = {
  BPI: { lengths: [10], groups: [4, 3, 3], example: "1234 567 890" },
  BDO: { lengths: [10, 12], groups: [4, 4, 4], example: "0012 3456 7890" },
  Metrobank: { lengths: [13], groups: [3, 3, 5, 2], example: "123 456 78901 2" },
  Landbank: { lengths: [10], groups: [4, 4, 2], example: "1234 5678 90" },
  UnionBank: { lengths: [12], groups: [4, 4, 4], example: "1234 5678 9012" },
  mobile: { lengths: [11], groups: [4, 3, 4], example: "0917 123 4567", prefix: "09" },
};

export const accountFormat = (provider) => (OLD_EWALLETS.includes(provider) ? ACCOUNT_FORMATS.mobile : ACCOUNT_FORMATS[provider]) ?? null;

/** Digits only, cut to the longest allowed length and spaced into groups */
export function formatAccount(provider, value) {
  const f = accountFormat(provider);
  const digits = String(value ?? "").replace(/\D/g, "");
  if (!f) return digits.slice(0, 20).replace(/(\d{4})(?=\d)/g, "$1 ");
  const cut = digits.slice(0, Math.max(...f.lengths));
  const parts = [];
  let i = 0;
  for (const g of f.groups) {
    if (i >= cut.length) break;
    parts.push(cut.slice(i, i + g));
    i += g;
  }
  if (i < cut.length) parts.push(cut.slice(i));
  return parts.join(" ");
}

/** An error to show, or "" when the number fits the bank or e-wallet */
export function accountError(provider, value) {
  const f = accountFormat(provider);
  const digits = String(value ?? "").replace(/\D/g, "");
  if (!digits) return "Enter the account or mobile number.";
  if (!f) return digits.length >= 6 ? "" : "Enter at least 6 digits.";
  if (f.prefix && !digits.startsWith(f.prefix)) return `Mobile numbers start with ${f.prefix}, e.g. ${f.example}.`;
  if (!f.lengths.includes(digits.length)) {
    const want = f.lengths.join(" or ");
    return `${OLD_EWALLETS.includes(provider) ? "The mobile number" : `A ${provider} account number`} has ${want} digits, e.g. ${f.example}.`;
  }
  return "";
}

/** "10 digits", "10 or 12 digits", shown under the field */
export const accountHint = (provider) => {
  const f = accountFormat(provider);
  return f ? `${f.lengths.join(" or ")} digits · e.g. ${f.example}` : "";
};
