// Pure helpers for the shift planner board (tests/scheduleMath.test.mjs).

export const WEEKDAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];

const pad = (n) => String(n).padStart(2, "0");
export const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
export const parseDay = (s) => new Date(`${s}T00:00:00`);
export const addDays = (s, n) => {
  const d = parseDay(s);
  d.setDate(d.getDate() + n);
  return ymd(d);
};
export const weekStart = (s) => {
  const d = parseDay(s);
  d.setDate(d.getDate() - ((d.getDay() + 6) % 7));
  return ymd(d);
};
export const weekdayName = (s) => WEEKDAYS[(parseDay(s).getDay() + 6) % 7];

/** The days the board loads: one week, or whole weeks covering a month. */
export function rangeFor(view, anchor) {
  if (view === "week") {
    const from = weekStart(anchor);
    return { from, to: addDays(from, 6) };
  }
  const d = parseDay(anchor);
  const first = ymd(new Date(d.getFullYear(), d.getMonth(), 1));
  const last = ymd(new Date(d.getFullYear(), d.getMonth() + 1, 0));
  const from = weekStart(first);
  return { from, to: addDays(weekStart(last), 6) };
}

/** Which board row an assignment sits in */
export const rowKey = (a) => (a.kind === "off" ? "off" : a.kind === "custom" ? "custom" : `t${a.template_id}`);

/** Assignments grouped by "row|date" */
export function cells(assignments) {
  const out = {};
  for (const a of assignments) (out[`${rowKey(a)}|${a.date}`] ??= []).push(a);
  for (const list of Object.values(out)) list.sort((x, y) => Number(x.status === "replaced") - Number(y.status === "replaced") || x.employee.localeCompare(y.employee));
  return out;
}

/** How a shift is staffed on a day: who's actually on it vs how many it needs. */
export function coverage(template, list = [], date) {
  const runs = template.days.includes(weekdayName(date));
  const on = list.filter((a) => a.status !== "replaced").length;
  const need = runs ? template.headcount : 0;
  return { on, need, runs, short: Math.max(0, need - on), state: !runs && !on ? "idle" : on < need ? "short" : on > need ? "over" : "ok" };
}

export const AVAILABILITY_GROUPS = [
  { key: "free", label: "Free", hint: "No hours set — ready to schedule", draggable: true },
  { key: "usual", label: "On usual hours", hint: "Not on the board; works their usual hours", draggable: true },
  { key: "working", label: "On a shift", hint: "Drag to move them", draggable: true },
  { key: "resting", label: "Resting", hint: "Rest day or day off — asks first", draggable: true },
  { key: "covered", label: "Covered", hint: "Someone took their shift", draggable: false },
  { key: "leave", label: "On leave", hint: "Can't be scheduled", draggable: false },
];

const GROUP_OF = { free: "free", usual: "usual", shift: "working", custom: "working", rest: "resting", off: "resting", replaced: "covered", leave: "leave" };

/** Staff for one day, grouped for the availability panel */
export function availability(staff, date, query = "") {
  const q = query.trim().toLowerCase();
  const groups = Object.fromEntries(AVAILABILITY_GROUPS.map((g) => [g.key, []]));
  for (const p of staff) {
    if (q && ![p.name, p.position, p.department].some((v) => v && v.toLowerCase().includes(q))) continue;
    const day = p.availability[date];
    const group = GROUP_OF[day?.status];
    if (!group) continue; // not hired yet
    groups[group].push({ ...p, day, weekHours: p.week_hours[weekStart(date)] ?? 0 });
  }
  return AVAILABILITY_GROUPS.map((g) => ({ ...g, people: groups[g.key] })).filter((g) => g.people.length);
}

export const REASONS = [
  { key: "no_show", label: "Didn't show up", hint: "Still counts as absent" },
  { key: "sick", label: "Sick or emergency", hint: "Excused" },
  { key: "swap", label: "Swapped shifts", hint: "Excused" },
  { key: "other", label: "Other", hint: "Excused" },
];
export const REASON_LABEL = Object.fromEntries(REASONS.map((r) => [r.key, r.label]));

export function time12(hm) {
  if (!hm) return "";
  const [h, m] = hm.split(":").map(Number);
  const suffix = h >= 12 ? "PM" : "AM";
  const hour = h % 12 || 12;
  return m ? `${hour}:${pad(m)} ${suffix}` : `${hour} ${suffix}`;
}
