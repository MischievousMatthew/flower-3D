// Words, colours and date helpers shared by the Orders & Fulfillment pages.

export const STATUS_TONE = {
  pending: "amber",
  to_processed: "blue",
  to_ship: "violet",
  to_received: "teal",
  completed: "green",
  cancelled: "slate",
  returned: "red",
  refunded: "red",
  unpaid_checkout: "slate",
};

// One line explaining each stage, shown under the status on the details page
export const STATUS_HINT = {
  pending: "New order. Confirm it to start preparing.",
  to_processed: "Being prepared. Mark it ready once it's arranged and packed.",
  to_ship: "Ready. Send it out when the rider leaves.",
  to_received: "On its way to the customer.",
  completed: "Delivered.",
  cancelled: "This order was cancelled.",
  returned: "The customer sent this order back.",
  refunded: "This order was refunded.",
  unpaid_checkout: "Not an order yet. The customer didn't finish paying online, so there's nothing for you to do.",
};

export const CASE_STAGE = {
  awaiting_vendor: "Needs your reply",
  awaiting_customer: "Waiting for customer",
  awaiting_return: "Item coming back",
  awaiting_refund: "Send the refund",
  confirm_refund: "Refund sent",
  awaiting_replacement: "Send replacement",
  confirm_replacement: "Replacement sent",
  completed: "Settled",
  rejected: "Declined",
  withdrawn: "Withdrawn",
};

export const PAYMENT_LABEL = { cod: "Cash on delivery", ewallet: "E-wallet", gcash: "GCash", maya: "Maya", card: "Card" };

const peso = new Intl.NumberFormat("en-PH", { style: "currency", currency: "PHP" });
export const money = (v) => peso.format(Number(v || 0));

const MANILA = "Asia/Manila";
export const todayKey = () => new Date().toLocaleDateString("en-CA", { timeZone: MANILA });
const addDays = (key, n) => {
  const d = new Date(`${key}T00:00:00`);
  d.setDate(d.getDate() + n);
  return d.toLocaleDateString("en-CA");
};

/** "Today", "Tomorrow", "Yesterday", "Mon, Sep 29" */
export function dayLabel(key, { long = false } = {}) {
  if (!key) return "No date";
  const today = todayKey();
  if (key === today) return "Today";
  if (key === addDays(today, 1)) return "Tomorrow";
  if (key === addDays(today, -1)) return "Yesterday";
  const d = new Date(`${key}T00:00:00`);
  return d.toLocaleDateString("en-PH", long
    ? { weekday: "long", month: "long", day: "numeric" }
    : { weekday: "short", month: "short", day: "numeric" });
}

/** Whole days from today to a date (negative = past) */
export function daysFromToday(key) {
  if (!key) return null;
  return Math.round((new Date(`${key}T00:00:00`) - new Date(`${todayKey()}T00:00:00`)) / 86_400_000);
}

export function timeAgo(iso) {
  if (!iso) return "";
  const mins = Math.round((Date.now() - new Date(iso)) / 60000);
  if (mins < 1) return "just now";
  if (mins < 60) return `${mins} min ago`;
  const hours = Math.round(mins / 60);
  if (hours < 24) return `${hours} h ago`;
  const days = Math.round(hours / 24);
  return days < 30 ? `${days} d ago` : new Date(iso).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" });
}

export const formatDateTime = (iso) =>
  iso ? new Date(iso).toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" }) : "";

/** Is this order late for its delivery date? */
export const isLate = (o) =>
  !!o.delivery_date && !["completed", "cancelled", "returned", "refunded", "unpaid_checkout"].includes(o.status) && daysFromToday(o.delivery_date) < 0;

/** Should preparation have started by now? */
export const shouldPrepare = (o) =>
  ["pending", "to_processed"].includes(o.status) && !!o.prepare_by && daysFromToday(o.prepare_by) <= 0;

export function parseCustomizations(value) {
  if (value && typeof value === "object") return value;
  try {
    return typeof value === "string" ? JSON.parse(value) : {};
  } catch {
    return {};
  }
}

/**
 * The custom 3D bouquet in an order, or null. Either one item carries the
 * whole arrangement (flowers, wrapper, ribbon, fillers), or older orders have
 * one item per placed flower. `name` and `product_name` are both accepted.
 */
export function bouquetFromItems(items = []) {
  const custom = (i) => parseCustomizations(i?.customizations) ?? {};
  const whole = items.map(custom).find((c) => c?.type === "custom_flower_bouquet" && Array.isArray(c.flowers));
  if (whole) return whole;
  const placed = items.filter((i) => custom(i)?.type === "custom_flower_bouquet" && custom(i)?.placement);
  if (!placed.length) return null;
  const first = custom(placed[0]);
  return {
    type: "custom_flower_bouquet",
    bouquet_model_url: "/Boquet.glb",
    paper_color: first.paper_color,
    ribbon_color: first.ribbon_color,
    flowers: placed.map((i, index) => ({
      ...custom(i).placement,
      product_id: i.product_id,
      product_name: i.name ?? i.product_name,
      quantity: i.quantity,
      placement_order: index,
      scale: custom(i).placement.scale || { x: 1, y: 1, z: 1 },
    })),
  };
}

const cents = (n) => Math.round(Number(n || 0) * 100) / 100;

/**
 * The custom bouquets in an order, each as one priced breakdown:
 * flowers (count × price), the occasion's base price (which covers the wrapper,
 * ribbon and fillers), the message card with its message, and the total.
 *
 * A bouquet is saved either as one order item carrying every flower, or as one
 * item per flower with the base price and card added to the first flower's price.
 * Items with the same arrangement belong to the same bouquet.
 */
export function bouquetBreakdowns(items = []) {
  const custom = (i) => parseCustomizations(i?.customizations) ?? {};
  const groups = new Map();
  for (const item of items) {
    const c = custom(item);
    if (c.type !== "custom_flower_bouquet" || !Array.isArray(c.flowers)) continue;
    const key = c.flowers.map((f) => f.scene_id ?? `${f.product_id}-${f.placement_order}`).join("|") || `item-${item.id}`;
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(item);
  }

  return [...groups.values()].map((group) => {
    const c = custom(group[0]);
    const total = cents(group.reduce((sum, i) => sum + Number(i.subtotal || 0), 0));
    const card = c.card?.id || c.card?.name ? c.card : null;
    const cardPrice = cents(card?.price);
    const extras = cents(c.extras_total ?? 0);
    const partsPrice = cents(
      ["wrapper", "inner_wrapper", "ribbon"].reduce((s, k) => s + Number(c[k]?.price || 0), 0) +
        (c.fillers || []).reduce((s, f) => s + Number(f.price || 0), 0),
    );
    const base = Math.max(0, cents(extras - cardPrice - partsPrice));
    const flowersTotal = cents(total - extras);

    // Flowers by kind, in the order they were placed
    const kinds = new Map();
    for (const f of c.flowers) {
      const id = f.product_id;
      const kind = kinds.get(id) ?? { product_id: id, name: f.product_name || "Flower", count: 0, listed: 0, image: null };
      kind.count += Number(f.quantity || 1);
      kind.listed += Number(f.price || 0) * Number(f.quantity || 1);
      kinds.set(id, kind);
    }
    const flowers = [...kinds.values()];
    for (const kind of flowers) {
      kind.image = group.find((i) => i.product_id === kind.product_id && i.image)?.image ?? null;
      // One item per flower: what was charged for that kind, less the extras on the first item
      const charged = group.filter((i) => i.product_id === kind.product_id);
      if (group.length > 1 && charged.length) {
        const firstHasExtras = group[0].product_id === kind.product_id;
        kind.subtotal = cents(charged.reduce((s, i) => s + Number(i.subtotal || 0), 0) - (firstHasExtras ? extras : 0));
      } else {
        kind.subtotal = cents(kind.listed);
      }
    }
    // A single kind takes whatever the flowers came to, so the lines always add up to the total
    if (flowers.length === 1) flowers[0].subtotal = flowersTotal;
    for (const kind of flowers) kind.unit_price = kind.count ? cents(kind.subtotal / kind.count) : 0;

    const part = (key, label) =>
      c[key] || (key === "wrapper" && c.paper_color) || (key === "inner_wrapper" && c.inner_paper_color) || (key === "ribbon" && c.ribbon_color)
        ? {
            key,
            label,
            name: c[key]?.name || "",
            color: c[key]?.color_hex || c[{ wrapper: "paper_color", inner_wrapper: "inner_paper_color", ribbon: "ribbon_color" }[key]] || null,
            pattern: wrapperPatternLabel(key === "wrapper" ? c.wrapper_pattern : key === "inner_wrapper" ? c.inner_wrapper_pattern : null),
            price: cents(c[key]?.price),
          }
        : null;
    const fillerKinds = new Map();
    for (const f of c.fillers || []) {
      const k = fillerKinds.get(f.id ?? f.name) ?? { name: f.name || "Filler", count: 0, price: 0 };
      k.count += 1;
      k.price = cents(k.price + Number(f.price || 0));
      fillerKinds.set(f.id ?? f.name, k);
    }

    return {
      key: group.map((i) => i.id).join("-"),
      preset: c.preset?.key ? { key: c.preset.key, label: c.preset.label, size: c.preset.size } : null,
      design: c.design || null,
      flowers,
      stems: flowers.reduce((s, f) => s + f.count, 0),
      base,
      parts: [part("wrapper", "Wrapper"), part("inner_wrapper", "Inner wrapper"), part("ribbon", "Ribbon")].filter(Boolean),
      fillers: [...fillerKinds.values()],
      card: card
        ? { name: card.name || "Message card", size: card.size || "", price: cardPrice, message: String(card.message || "").trim() }
        : null,
      total,
      notes: group.map((i) => i.notes).find(Boolean) || "",
      item_ids: group.map((i) => i.id),
    };
  });
}

/** Whether an order item is (part of) a custom 3D bouquet */
export const isBouquetItem = (item) => parseCustomizations(item?.customizations)?.type === "custom_flower_bouquet";

/** What staff need to make a bouquet: flowers by count, wrappers, ribbon, fillers, size */
/** "Newspaper print" or "Words: “love”" for a wrapper pattern; "" for plain paper */
export function wrapperPatternLabel(pattern) {
  if (pattern?.type === "newspaper") return "Newspaper print";
  if (pattern?.type === "words" && String(pattern.text || "").trim()) return `Words: “${String(pattern.text).trim()}”`;
  return "";
}

export function bouquetRecipe(bouquet) {
  if (!bouquet) return null;
  const counts = new Map();
  for (const f of bouquet.flowers ?? []) {
    const name = f.product_name || "Flower";
    counts.set(name, (counts.get(name) ?? 0) + Number(f.quantity || 1));
  }
  const swatch = (part, fallbackColor) =>
    part || fallbackColor ? { name: part?.name ?? null, color: part?.color_hex ?? fallbackColor ?? null } : null;
  return {
    flowers: [...counts].map(([name, count]) => ({ name, count })),
    flowerTotal: [...counts.values()].reduce((a, b) => a + b, 0),
    wrapper: swatch(bouquet.wrapper, bouquet.paper_color),
    innerWrapper: swatch(bouquet.inner_wrapper, bouquet.inner_paper_color),
    pattern: wrapperPatternLabel(bouquet.wrapper_pattern),
    innerPattern: wrapperPatternLabel(bouquet.inner_wrapper_pattern),
    ribbon: swatch(bouquet.ribbon, bouquet.ribbon_color),
    fillers: [...new Set((bouquet.fillers ?? []).map((f) => f.name).filter(Boolean))],
    occasion: bouquet.preset?.label ?? null,
  };
}
