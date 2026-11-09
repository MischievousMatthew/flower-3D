// Banks and e-wallets a funding request is paid through: only those PayMongo
// Checkout offers. Matches App\Support\PayMongoChannels on the server.
export const CHANNELS = {
  bank: ["BPI", "UnionBank", "BDO", "Landbank", "Metrobank"],
  ewallet: ["GCash", "Maya", "GrabPay", "ShopeePay"],
};

export const kindOf = (channel) => (CHANNELS.ewallet.includes(channel) ? "ewallet" : "bank");

// PayMongo's smallest e-wallet and online-banking payment
export const PAYMONGO_MINIMUM = 20;
