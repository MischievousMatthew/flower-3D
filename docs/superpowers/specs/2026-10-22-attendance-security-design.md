# Attendance: anti-spoofing, shop-only kiosks, reviews and reports

## Goal

Make QR + face attendance hard to cheat (videos, screenshots, faked requests), allow it only on
owner-approved devices at the shop, and round out the module: daily logs, late / absent /
undertime, corrections with approval, summaries and reports.

## Decisions (from the shop owner)

- **Location:** the scanner works only on an owner-approved device whose GPS is inside the shop
  radius. HR can use the rest of the ERP anywhere.
- **Devices:** the owner approves 1–3 devices in the vendor portal. A device can only be
  requested from inside the shop.
- **Suspicious scans** are saved as "Needs review" and count for payroll only after approval.
- **Faces not enrolled yet:** scanning still works with the profile photo, but every such scan
  needs review.
- **The owner** approves devices. Reviews and corrections: the owner, or HR with
  `attendance.approve`, never the person who asked.

## How a scan works

1. The kiosk page loads with the device token (kept in the browser). The server reports the
   device state: none, pending, approved, or removed.
2. HR scans the employee's QR. The browser sends the QR text, GPS position and device token to
   `POST /attendance/kiosk/sessions`. The server checks:
   - the device is approved;
   - the device is inside the radius;
   - the QR signature and version;
   - the employee is active.

   It then picks time in or time out and returns a one-time session that lasts 90 seconds, with
   4 challenges in a random order and a random 4-color flash sequence.
3. The browser runs the challenges in that order: blink/smile, turn left/right, move
   closer/back, and nod. It flashes the colors and measures how the face reflects them, and runs
   the Human anti-spoof and liveness models. It takes 3 small snapshots and a face descriptor.
4. `POST /attendance/kiosk/sessions/{id}/complete` checks:
   - the session is the device's own, not expired and not already used;
   - the position is still inside the radius;
   - the challenges are the server's, done in order, within time limits;
   - the descriptor matches the enrolled descriptors, with the distance computed on the server;
   - the anti-spoof and liveness scores.

   Clear failures are rejected. Borderline or weak signals save the scan as "Needs review" with
   the reasons. The snapshots go to Cloudinary as private files, kept as evidence.

The old endpoints that trusted the browser (`/attendance/scan`, `/attendance/time-in`,
`/attendance/time-out`) now answer 410 Gone.

## Signed QR codes

The QR payload keeps `type`, `owner_id`, `employee_id` and `employee_code`, and adds `v` (from
`employees_info.qr_version`) and `sig`. `sig` is an HMAC-SHA256 with the app key over
`owner|employee|version`.

- **Reissuing** a QR bumps the version, so old copies stop working.
- **Legacy unsigned codes** still scan, but those scans need review ("old QR code").

## Face enrollment

HR enrolls a face at an approved kiosk. They scan the QR, then the same liveness flow takes 5
captures. The server checks the 5 descriptors agree with each other and saves them
(`face_enrollments`). Re-enrolling replaces them. The descriptors never go back to the browser.

## Thresholds (server)

| Signal | OK | Needs review | Rejected |
|---|---|---|---|
| Face distance, enrolled | ≤ 0.45 | ≤ 0.55 | > 0.55 |
| Face distance vs profile photo, not enrolled | — | ≤ 0.50, always reviewed | > 0.50 |
| Anti-spoof / liveness score | ≥ 0.6 | ≥ 0.3 | < 0.3 |
| Flash response | ≥ 3 of 4 colors | fewer | — |
| GPS accuracy | ≤ 100 m | — | worse, asks for precise location |

Other rules:
- A device passes the location check when its distance is at most the radius + min(accuracy, 30 m).
- Each challenge must take 0.3–10 s. The whole session must take at least 4 s and finish before
  it expires.

## Corrections, reviews, calculations

- `attendance_corrections` holds requests: times plus a reason. Approving one applies it to the
  record, with source `correction`.
- In the ERP, direct edits and manual adds become correction requests.
- The owner's edits in the vendor portal apply directly, since the owner is the approver.
- `employee_attendance` gains these columns: `review_status`, `review_reasons`, `reviewed_by`,
  `reviewed_at`, `review_note`, `time_in_evidence`, `time_out_evidence`, `source`.
- Payroll skips records whose review is pending or rejected.
- `AttendanceCalculator` works out the expected hours for a day: the schedule shift, otherwise
  the usual hours minus rest days. It also works out late minutes (after the grace period),
  undertime and absences (a scheduled day with no record and no approved leave), and per-person
  summaries. It is shared by the vendor and ERP pages.
- `attendance_settings` per shop holds: latitude and longitude (they default to the store pin
  from the vendor application), radius (default 100 m) and late grace (default 10 min).

## Screens

- **Vendor → Staff → Attendance devices:** the shop location and radius, and the devices list
  (approve up to 3, rename, remove).
- **Vendor → Staff → Attendance:** adds a review queue and late/absent/undertime figures.
- **ERP → HR → Attendance:**
  - QR Scanner: device gate, scanning, face enrollment.
  - Logs: daily log with late/undertime/absent markers and correction requests.
  - Review: flagged scans and corrections.
  - Reports: summary and CSV.

  All of these use the vendor look (`HrPage`, `meta.erpTheme`).

## Limits

The browser-side signals (flash, anti-spoof, challenge timing) can be faked by someone who edits
the page code. What a faker cannot get around: the server-checked session, approved devices,
signed QR codes, server-side face matching against enrolled faces, and the evidence photos that
HR reviews.
