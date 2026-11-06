/**
 * Announcement bar colours. Each one has its own icon on the shop, so the
 * admin picks the colour by that icon (Admin → Promotion Management).
 * `swatch` / `ink` are the bar's own background and text colours.
 */
export const ANNOUNCEMENT_TONES = [
  {
    value: "brand",
    label: "Dark (megaphone)",
    swatch: "#363f4e",
    ink: "#8fd1a6",
    icon: '<path d="M3 11l15-6v14L3 13v-2z"/><path d="M18 9a3 3 0 0 1 0 6"/><path d="M7 13.5V18a2 2 0 0 0 4 0v-3"/>',
  },
  {
    value: "info",
    label: "Blue (information)",
    swatch: "#eaf0fa",
    ink: "#1e3a6b",
    icon: '<circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 7.5h.01"/>',
  },
  {
    value: "success",
    label: "Green (star)",
    swatch: "#e8f5ec",
    ink: "#2e7a4f",
    icon: '<path d="M12 3l2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 15.4 7.2 17.9l.9-5.4L4.2 8.7l5.4-.8L12 3z"/>',
  },
  {
    value: "warning",
    label: "Amber (warning)",
    swatch: "#fdf3e1",
    ink: "#8a5300",
    icon: '<path d="M12 4l9 16H3l9-16z"/><path d="M12 10v4"/><path d="M12 17h.01"/>',
  },
];

export const ANNOUNCEMENT_ICONS = Object.fromEntries(ANNOUNCEMENT_TONES.map((t) => [t.value, t.icon]));
