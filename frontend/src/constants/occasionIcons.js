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
};
