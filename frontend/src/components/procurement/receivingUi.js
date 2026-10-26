// Shared wording for receiving & quality inspection.

export const CONDITIONS = [
  { value: "fresh", label: "Fresh", hint: "Firm, closed or just opening", tone: "green" },
  { value: "slightly_open", label: "Slightly open", hint: "Fine, but sell these first", tone: "teal" },
  { value: "wilting", label: "Wilting", hint: "Drooping or soft", tone: "amber" },
  { value: "damaged", label: "Damaged", hint: "Bruised, broken or crushed", tone: "red" },
];

export const CHECKS = [
  { value: "stems_firm", label: "Stems firm & straight" },
  { value: "heads_intact", label: "Heads intact" },
  { value: "no_pests", label: "No pests or mold" },
  { value: "right_variety", label: "Right variety & color" },
  { value: "packed_well", label: "Packed well" },
];

export const REJECT_REASONS = [
  { value: "wilted", label: "Wilted" },
  { value: "damaged", label: "Damaged or bruised" },
  { value: "pests_mold", label: "Pests or mold" },
  { value: "wrong_item", label: "Wrong item" },
  { value: "short_stems", label: "Stems too short" },
  { value: "wrong_color", label: "Wrong color" },
  { value: "other", label: "Other" },
];

export const conditionOf = (v) => CONDITIONS.find((c) => c.value === v) ?? null;
export const reasonText = (v) => REJECT_REASONS.find((r) => r.value === v)?.label ?? "";
export const checkText = (v) => CHECKS.find((c) => c.value === v)?.label ?? v;
