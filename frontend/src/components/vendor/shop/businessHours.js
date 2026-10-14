// Weekly business hours, shared by My Shop (preview) and Shop settings (editor).
// Stored shape: [{ days: ["Monday", …], opening_time: "08:00", closing_time: "17:00" }]

export const DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

/** Stored schedules → one row per day for the editor */
export const weekFrom = (schedules) =>
  DAYS.map((day) => {
    const s = (schedules ?? []).find((x) => (x.days ?? []).includes(day));
    return { day, open: !!s, opening_time: s?.opening_time ?? "08:00", closing_time: s?.closing_time ?? "17:00" };
  });

// Best guess from the old free-text hours: which days, and the first time range in it
export function weekFromText(text) {
  const t = String(text || "");
  const toH24 = (h, m, ap) => {
    let hour = Number(h) % 12;
    if (/p/i.test(ap)) hour += 12;
    return `${String(hour).padStart(2, "0")}:${m}`;
  };
  const times = [...t.matchAll(/(\d{1,2}):(\d{2})\s*([AaPp])\.?[Mm]/g)];
  const opening = times[0] ? toH24(times[0][1], times[0][2], times[0][3]) : "08:00";
  const closing = times[1] ? toH24(times[1][1], times[1][2], times[1][3]) : "17:00";
  const everyDay = /every\s*day|daily|mon\s*-\s*sun/i.test(t);
  return DAYS.map((day, i) => ({ day, open: everyDay || i < 6, opening_time: opening, closing_time: closing }));
}

/** Group days with identical hours back into the stored schedule shape */
export const schedulesFrom = (days) => {
  const groups = [];
  for (const d of days.filter((x) => x.open)) {
    const g = groups.find((x) => x.opening_time === d.opening_time && x.closing_time === d.closing_time);
    if (g) g.days.push(d.day);
    else groups.push({ days: [d.day], opening_time: d.opening_time, closing_time: d.closing_time });
  }
  return groups;
};

export const to12 = (t) => {
  const [h, m] = t.split(":").map(Number);
  return `${h % 12 || 12}:${String(m).padStart(2, "0")} ${h >= 12 ? "PM" : "AM"}`;
};

/** "Mon–Sat 8:00 AM–5:00 PM · Sun 9:00 AM–3:00 PM" (runs of 3+ days are shortened) */
export function hoursText(week) {
  const groups = schedulesFrom(week);
  if (!groups.length) return "";
  const short = (days) => {
    if (days.length === 7) return "Every day";
    const idx = days.map((d) => DAYS.indexOf(d)).sort((a, b) => a - b);
    const parts = [];
    for (let i = 0; i < idx.length; ) {
      let j = i;
      while (j + 1 < idx.length && idx[j + 1] === idx[j] + 1) j++;
      const name = (k) => DAYS[idx[k]].slice(0, 3);
      if (j - i >= 2) parts.push(`${name(i)}–${name(j)}`);
      else for (let k = i; k <= j; k++) parts.push(name(k));
      i = j + 1;
    }
    return parts.join(", ");
  };
  return groups.map((g) => `${short(g.days)} ${to12(g.opening_time)}–${to12(g.closing_time)}`).join(" · ");
}

/** Open right now in Philippine time? null when there are no hours yet */
export function openNow(week) {
  if (!week.length) return null;
  const now = new Date(new Date().toLocaleString("en-US", { timeZone: "Asia/Manila" }));
  const today = week[(now.getDay() + 6) % 7];
  const hhmm = `${String(now.getHours()).padStart(2, "0")}:${String(now.getMinutes()).padStart(2, "0")}`;
  return today.open && hhmm >= today.opening_time && hhmm < today.closing_time;
}
