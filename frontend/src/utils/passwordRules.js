// The password rules the server enforces: a minimum length (8, or 10 for
// administrators), an uppercase letter, a lowercase letter and a number
export const passwordRules = (pw = "", min = 8) => [
  { label: `${min} or more characters`, met: pw.length >= min },
  { label: "An uppercase letter", met: /[A-Z]/.test(pw) },
  { label: "A lowercase letter", met: /[a-z]/.test(pw) },
  { label: "A number", met: /\d/.test(pw) },
];
export const passwordOk = (pw, min = 8) => passwordRules(pw, min).every((r) => r.met);
/** How much of the list is done, 0–100 */
export const passwordPercent = (pw, min = 8) => {
  const rules = passwordRules(pw, min);
  return Math.round((rules.filter((r) => r.met).length / rules.length) * 100);
};
