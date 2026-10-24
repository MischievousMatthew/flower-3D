// Shared by the shop's Customers page and the customer's own profile

export const SUGGEST = {
  flowers: ["Roses", "Tulips", "Sunflowers", "Lilies", "Peonies", "Orchids", "Carnations", "Gerberas", "Chrysanthemums", "Hydrangeas", "Baby's breath", "Eucalyptus"],
  colors: ["Red", "Pink", "White", "Pastel", "Yellow", "Purple", "Blue", "Peach", "Mixed"],
  avoid: ["Lilies (pollen)", "Strong scents", "Thorns", "Dyed flowers"],
  occasions: ["Birthday", "Anniversary", "Valentine's Day", "Mother's Day", "Graduation", "Get well", "Sympathy", "Just because"],
};

// SVG icons (Lucide shapes) — the pages use these instead of emoji
const svg = (body, filled = false) =>
  `<svg viewBox="0 0 24 24" width="18" height="18" fill="${filled ? "currentColor" : "none"}" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${body}</svg>`;
export const CRM_ICONS = {
  birthday: svg('<path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/><path d="M4 16s.5-1 2-1 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2-1 2-1"/><path d="M2 21h20"/><path d="M7 8v3M12 8v3M17 8v3"/><path d="M7 4h.01M12 4h.01M17 4h.01"/>'),
  anniversary: svg('<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>'),
  other: svg('<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>'),
  heart: svg('<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>', true),
  star: svg('<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>', true),
  starOutline: svg('<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>'),
  pin: svg('<path d="M12 17v5"/><path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/>'),
  flower: svg('<circle cx="12" cy="12" r="3"/><path d="M12 16.5A4.5 4.5 0 1 1 7.5 12 4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 1 1 4.5 4.5 4.5 4.5 0 1 1-4.5 4.5"/><path d="M12 7.5V9M7.5 12H9M16.5 12H15M12 16.5V15"/>'),
  close: svg('<path d="M18 6 6 18M6 6l12 12"/>'),
};

export const DATE_KINDS = [
  { value: "birthday", label: "Birthday", icon: CRM_ICONS.birthday },
  { value: "anniversary", label: "Anniversary", icon: CRM_ICONS.anniversary },
  { value: "other", label: "Other day", icon: CRM_ICONS.other },
];
export const kindOf = (k) => DATE_KINDS.find((d) => d.value === k) ?? DATE_KINDS[2];

export const MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
export const daysIn = (month) => (month ? new Date(2024, month, 0).getDate() : 31);

/** "Oct 25" */
export const monthDay = (d) => `${MONTHS[d.month - 1].slice(0, 3)} ${d.day}`;

/** "today", "tomorrow", "in 5 days" */
export function whenText(daysAway) {
  if (daysAway === 0) return "today";
  if (daysAway === 1) return "tomorrow";
  if (daysAway < 14) return `in ${daysAway} days`;
  if (daysAway < 60) return `in ${Math.round(daysAway / 7)} weeks`;
  return `in ${Math.round(daysAway / 30)} months`;
}

export const blankDate = () => ({ kind: "birthday", label: "", month: "", day: "", year: "", note: "" });

/** Fills in a label from the kind when left empty */
export function datePayload(f) {
  return {
    kind: f.kind,
    label: f.label.trim() || kindOf(f.kind).label,
    month: Number(f.month),
    day: Number(f.day),
    year: f.year ? Number(f.year) : null,
    note: f.note?.trim() || null,
  };
}
