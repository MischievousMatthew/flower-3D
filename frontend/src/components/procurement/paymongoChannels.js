// Banks and e-wallets a funding request is paid through: only those PayMongo
// Checkout offers. Matches App\Support\PayMongoChannels on the server.
export const CHANNELS = {
  // QR Ph: a QR code the payer scans with almost any bank or e-wallet app
  bank: ["QR Ph", "BPI", "UnionBank", "BDO", "Landbank", "Metrobank"],
  ewallet: ["GCash", "Maya", "GrabPay", "ShopeePay"],
};

// Accounts a supplier can be paid into (a QR code can't receive money)
export const PAYOUT_CHANNELS = { bank: CHANNELS.bank.filter((c) => c !== "QR Ph"), ewallet: CHANNELS.ewallet };

export const kindOf = (channel) => (CHANNELS.ewallet.includes(channel) ? "ewallet" : "bank");

// PayMongo's smallest e-wallet and online-banking payment
export const PAYMONGO_MINIMUM = 20;
