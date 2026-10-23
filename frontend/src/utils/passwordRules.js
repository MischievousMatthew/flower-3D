// The password rules the server enforces (min 8, upper, lower, number)
export const passwordRules = (pw = "") => [
  { label: "8 or more characters", met: pw.length >= 8 },
  { label: "An uppercase letter", met: /[A-Z]/.test(pw) },
  { label: "A lowercase letter", met: /[a-z]/.test(pw) },
  { label: "A number", met: /\d/.test(pw) },
];
export const passwordOk = (pw) => passwordRules(pw).every((r) => r.met);
