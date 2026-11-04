// Line icons for the bouquet occasions (24px grid, stroke = currentColor).
// Shared by the vendor's Occasions tab and the customer's customizer.
const icon = (d) =>
  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${d}</svg>`;

export const OCCASION_ICONS = {
  rings: icon('<circle cx="9" cy="14" r="5"/><circle cx="15" cy="14" r="5"/><path d="m10 5 2-2 2 2-2 2z"/>'),
  heart: icon('<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10Z"/>'),
  gift: icon('<rect x="3" y="9" width="18" height="12" rx="1.5"/><path d="M3 13h18M12 9v12"/><path d="M12 9S10.5 4 8 4.6C5.7 5.2 7 9 12 9Zm0 0s1.5-5 4-4.4C18.3 5.2 17 9 12 9Z"/>'),
  cap: icon('<path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5M22 9v6"/>'),
  dove: icon('<path d="M3 17c5 0 8-2 9-6l3-6c1.5 2.5 1 5-.5 7 2 0 4.5-.5 6.5-2-1 5-5 8-11 8H5z"/><path d="M8 17l-2 3"/>'),
  flower: icon('<circle cx="12" cy="9" r="2.5"/><path d="M12 6.5a3 3 0 1 1 3 3 3 3 0 1 1-3 3 3 3 0 1 1-3-3 3 3 0 1 1 3-3Z"/><path d="M12 13v8M12 18c2 0 3.5-1 4-3"/>'),
  // The shop's occasion tags (Shop by Occasion): one drawn icon each
  cake: icon('<path d="M4 21h16M5 21v-7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7"/><path d="M5 16c1.5 1 3 1 4.5 0s3-1 4.5 0 3 1 4.5 0M12 12V9M12 6.5c.8-.8.8-1.7 0-2.5-.8.8-.8 1.7 0 2.5Z"/>'),
  hearts: icon('<path d="M9.5 18S4 14.6 4 10.2A3.2 3.2 0 0 1 9.5 8a3.2 3.2 0 0 1 5.5 2.2"/><path d="M15.5 20s-4.5-2.8-4.5-6.4a2.6 2.6 0 0 1 4.5-1.8 2.6 2.6 0 0 1 4.5 1.8c0 3.6-4.5 6.4-4.5 6.4Z"/>'),
  lily: icon('<path d="M12 21v-8"/><path d="M12 13c-3 0-6-2.5-6-7 2.5 0 4.6 1.2 6 3.2C13.4 7.2 15.5 6 18 6c0 4.5-3 7-6 7Z"/><path d="M12 9.2V3.5M8 17c2 0 4 1 4 3M16 17c-2 0-4 1-4 3"/>'),
  sun: icon('<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6l1.4 1.4M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4 6 18M18 6l1.4-1.4"/>'),
  sparkle: icon('<path d="M12 3.5c.6 3.9 2.6 5.9 6.5 6.5-3.9.6-5.9 2.6-6.5 6.5-.6-3.9-2.6-5.9-6.5-6.5 3.9-.6 5.9-2.6 6.5-6.5Z"/><path d="M18.5 15.5c.3 1.7 1.1 2.5 2.8 2.8-1.7.3-2.5 1.1-2.8 2.8-.3-1.7-1.1-2.5-2.8-2.8 1.7-.3 2.5-1.1 2.8-2.8Z"/>'),
  tulip: icon('<path d="M7 5.5 9.5 8 12 4.5 14.5 8 17 5.5V10a5 5 0 0 1-10 0z"/><path d="M12 15v6M12 19c-2.2 0-4-1.4-4.5-3.5M12 19c2.2 0 4-1.4 4.5-3.5"/>'),
  tree: icon('<path d="M12 3 6.5 10H9l-4 6h14l-4-6h2.5z"/><path d="M12 16v5M9.5 21h5"/>'),
  briefcase: icon('<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V7M3 12.5h18M10.5 12.5v1.5h3v-1.5"/>'),
};

// Which icon an occasion tag gets, by its name (anything else gets the flower)
const OCCASION_NAME_ICONS = [
  [/wedding|bridal|engage/i, "rings"],
  [/birthday|bday/i, "cake"],
  [/valentine/i, "hearts"],
  [/anniversar|love|romance/i, "heart"],
  [/funeral|memorial/i, "lily"],
  [/sympathy|condolence/i, "dove"],
  [/get well|recovery/i, "sun"],
  [/congrat|celebrat|promotion/i, "sparkle"],
  [/graduat/i, "cap"],
  [/mother|father|parent/i, "tulip"],
  [/christmas|holiday/i, "tree"],
  [/corporate|business|office/i, "briefcase"],
  [/thank|gift/i, "gift"],
];

export function occasionIconFor(name) {
  const hit = OCCASION_NAME_ICONS.find(([pattern]) => pattern.test(String(name || "")));
  return OCCASION_ICONS[hit ? hit[1] : "flower"];
}
