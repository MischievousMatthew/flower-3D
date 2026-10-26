// Shared bits for HR profile pages in the vendor portal and the ERP.

export const EMPLOYEE_TABS = [
  { name: "EmployeeDirectory", label: "Directory", icon: "users" },
  { name: "EmployeeProfiles", label: "Profiles", icon: "user" },
];

export const ATTENDANCE_TABS = [
  { name: "AttendanceLogs", label: "Daily log", icon: "list" },
  { name: "AttendanceReview", label: "Review", icon: "shield" },
  { name: "AttendanceReports", label: "Reports", icon: "chart" },
  { name: "AttendanceQRScanner", label: "Scanner", icon: "grid" },
];

export const DAYS =["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
export const EMPLOYMENT_STATUSES = ["Active", "Inactive", "Probationary", "Regular", "Contractual", "Part-time"];

// The fields of an HR profile, as the backend validates them (EmployeeInfoController)
export const PERSONAL_FIELDS = [
  { key: "first_name", label: "First name", max: 100 },
  { key: "middle_name", label: "Middle name", max: 100, optional: true },
  { key: "last_name", label: "Last name", max: 100 },
  { key: "gender", label: "Gender", options: ["Male", "Female", "Other"] },
  { key: "date_of_birth", label: "Birthday", type: "date" },
  { key: "civil_status", label: "Civil status", options: ["Single", "Married", "Divorced", "Widowed"] },
];
export const CONTACT_FIELDS = [
  { key: "work_email", label: "Work email", type: "email", max: 150 },
  { key: "personal_email", label: "Personal email", type: "email", max: 150 },
  { key: "mobile_number", label: "Mobile", type: "tel", max: 20 },
  { key: "address", label: "Address", max: 500, wide: true },
];
export const EMERGENCY_FIELDS = [
  { key: "emergency_contact_name", label: "Name", max: 150 },
  { key: "emergency_relationship", label: "Relationship", max: 100, placeholder: "e.g. Mother" },
  { key: "emergency_contact_number", label: "Number", type: "tel", max: 20 },
];
export const JOB_FIELDS = [
  { key: "position", label: "Position", max: 150, placeholder: "e.g. Florist" },
  { key: "department", label: "Department", max: 150, placeholder: "e.g. Production" },
  { key: "employment_status", label: "Employment status", options: EMPLOYMENT_STATUSES },
  { key: "employment_type", label: "Type", options: ["Full-time", "Part-time"] },
  { key: "date_hired", label: "Hired on", type: "date" },
  { key: "work_location", label: "Work location", max: 200, placeholder: "e.g. Main shop" },
  { key: "reporting_manager", label: "Reports to", max: 150 },
];
export const REQUIRED_FIELDS = [
  "first_name", "last_name", "gender", "date_of_birth", "civil_status", "work_email", "personal_email", "mobile_number", "address",
  "emergency_contact_name", "emergency_relationship", "emergency_contact_number", "position", "department", "employment_status",
  "employment_type", "date_hired", "work_location", "reporting_manager", "shift_start", "shift_end",
];
export const PROFILE_KEYS = [...PERSONAL_FIELDS, ...CONTACT_FIELDS, ...EMERGENCY_FIELDS, ...JOB_FIELDS]
  .map((f) => f.key)
  .concat([
    "work_schedule", "shift_start", "shift_end", "salary_type", "basic_salary", "standard_work_hours_per_day",
    "working_days_per_week", "working_days_per_month", "payment_method", "bank_name", "account_number", "tax_status", "allowance",
  ]);

// Who has a team account (signs in to the ERP) and whose profile still has blanks (EmployeeInfo::scopeByAccess)
export const ACCESS_FILTERS = [
  { value: "account", label: "Has an account" },
  { value: "info", label: "Information only" },
  { value: "incomplete", label: "Details missing" },
];
/** "Birthday, Address and 3 more" */
export const missingSummary = (list = [], shown = 2) =>
  list.length <= shown ? list.join(", ") : `${list.slice(0, shown).join(", ")} and ${list.length - shown} more`;

export const STATUS_TONES = { Active: "green", Regular: "green", Probationary: "amber", Contractual: "blue", "Part-time": "violet", Inactive: "slate" };
export const statusTone = (status) => STATUS_TONES[status] ?? "slate";
export const PER = { daily: "a day", weekly: "a week", monthly: "a month", hourly: "an hour" };

export const initialsOf = (name) =>
  String(name ?? "")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join("");

export const day10 = (v) => (v ? String(v).slice(0, 10) : "");
export const hm5 = (v) => (v ? String(v).slice(0, 5) : "");
export const restDaysList = (text) =>
  String(text ?? "")
    .split(",")
    .map((d) => d.trim())
    .filter((d) => DAYS.includes(d));

/** Pay per hour from the payroll settings, as payroll computes it */
export function hourlyRate({ basic_salary, salary_type, standard_work_hours_per_day, working_days_per_week, working_days_per_month }) {
  const salary = parseFloat(basic_salary) || 0;
  const hours = parseFloat(standard_work_hours_per_day) || 8;
  if (!salary || !salary_type) return 0;
  if (salary_type === "weekly") return salary / (hours * (parseFloat(working_days_per_week) || 5));
  if (salary_type === "monthly") return salary / (hours * (parseFloat(working_days_per_month) || 22));
  return salary / hours;
}
