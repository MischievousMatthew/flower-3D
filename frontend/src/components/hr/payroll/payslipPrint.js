// A printable payslip in its own window (A5-ish card on an A4 page).
import { php, periodText } from "../../../services/payrollDesk";

const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
const rows = (list) => list.map((r) => `<tr><td>${esc(r.label)}</td><td class="n">${php(r.amount)}</td></tr>`).join("");

export function printPayslip(s) {
  const a = s.attendance;
  const html = `<!doctype html><html><head><meta charset="utf-8"><title>Payslip ${esc(s.number)}</title>
<style>
@page{size:A4;margin:14mm}*{box-sizing:border-box}body{margin:0;font-family:Poppins,Arial,sans-serif;color:#111827;font-size:12px}
.card{max-width:720px;margin:0 auto;border:1px solid #e5e7eb;border-radius:14px;padding:22px}
header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;border-bottom:2px solid #111827;padding-bottom:12px}
.shop{display:flex;gap:10px;align-items:center}.shop img{width:44px;height:44px;border-radius:10px;object-fit:cover}
h1{font-size:16px;margin:0}small,.muted{color:#6b7280}h2{font-size:20px;margin:0;text-align:right}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:6px 24px;margin:14px 0}.grid b{display:block;font-weight:600}
.cols{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:6px}
table{width:100%;border-collapse:collapse}td{padding:5px 0;border-bottom:1px solid #f3f4f6}.n{text-align:right;font-variant-numeric:tabular-nums}
th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#6b7280;padding-bottom:4px}
.total td{font-weight:700;border-bottom:none;padding-top:8px}
.net{margin-top:16px;display:flex;justify-content:space-between;align-items:center;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:12px 16px}
.net b{font-size:22px}.foot{margin-top:14px;display:flex;justify-content:space-between;color:#6b7280;font-size:11px}
.sign{margin-top:36px;display:grid;grid-template-columns:1fr 1fr;gap:40px}.sign div{border-top:1px solid #9ca3af;padding-top:4px;text-align:center;color:#6b7280}
</style></head><body><div class="card">
<header><div class="shop">${s.shop.logo ? `<img src="${esc(s.shop.logo)}" alt="">` : ""}<div><h1>${esc(s.shop.name)}</h1><small>${esc(s.shop.address)}</small></div></div>
<div><h2>Payslip</h2><small>${esc(s.number)}</small></div></header>
<div class="grid">
<div><small>Employee</small><b>${esc(s.employee.name)}</b><span class="muted">${esc([s.employee.code, s.employee.position].filter(Boolean).join(" · "))}</span></div>
<div><small>Pay period</small><b>${esc(periodText(s.period_start, s.period_end))}</b><span class="muted">${esc(s.salary_type)} rate ${php(s.basic_salary)}</span></div>
<div><small>Attendance</small><b>${a.days_worked} of ${a.expected_days} days · ${a.hours} h</b><span class="muted">${a.paid_leave_days} paid leave · ${a.absent_days} absent${a.overtime_hours ? ` · ${a.overtime_hours} h overtime` : ""}</span></div>
<div><small>Paid by</small><b>${esc(s.employee.payment_method || "—")}${s.employee.bank ? ` · ${esc(s.employee.bank)} ${esc(s.employee.account || "")}` : ""}</b><span class="muted">${s.paid_at ? `Paid ${new Date(s.paid_at).toLocaleDateString("en-PH")}` : esc(s.status)}</span></div>
</div>
<div class="cols">
<table><tr><th>Earnings</th><th></th></tr>${rows(s.earnings)}<tr class="total"><td>Gross pay</td><td class="n">${php(s.gross)}</td></tr></table>
<table><tr><th>Deductions</th><th></th></tr>${s.deductions.length ? rows(s.deductions) : `<tr><td class="muted">None</td><td></td></tr>`}<tr class="total"><td>Total deductions</td><td class="n">${php(s.total_deductions)}</td></tr></table>
</div>
<div class="net"><span>Net pay</span><b>${php(s.net)}</b></div>
<div class="foot"><span>Year to date: gross ${php(s.ytd.gross)} · net ${php(s.ytd.net)}</span><span>${s.approved_by ? `Approved by ${esc(s.approved_by)}` : ""}</span></div>
<div class="sign"><div>Prepared by ${esc(s.created_by || "")}</div><div>Received by ${esc(s.employee.name)}</div></div>
</div><script>window.onload=()=>window.print()<\/script></body></html>`;
  const win = window.open("", "_blank", "width=860,height=1000");
  if (!win) return false;
  win.document.write(html);
  win.document.close();
  return true;
}
