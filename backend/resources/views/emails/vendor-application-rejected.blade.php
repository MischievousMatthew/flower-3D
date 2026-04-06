<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Application Rejected</title>
</head>
<body style="margin:0;padding:0;background:#f7f2eb;font-family:Arial,sans-serif;color:#2f241b;">
    <div style="max-width:640px;margin:0 auto;padding:32px 20px;">
        <div style="background:#ffffff;border-radius:20px;padding:32px;border:1px solid rgba(122,92,60,.12);">
            <p style="margin:0 0 8px;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#9e7250;font-weight:700;">
                BloomCraft Vendor Review
            </p>
            <h1 style="margin:0 0 16px;font-size:28px;line-height:1.2;color:#2f241b;">
                Vendor application not approved
            </h1>

            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#5f4c3d;">
                Hello {{ $application->owner_name ?: 'Applicant' }},
            </p>

            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#5f4c3d;">
                We reviewed your vendor application for <strong>{{ $application->store_name ?: 'your store' }}</strong>.
                At this time, we cannot approve the application.
            </p>

            <div style="margin:24px 0;padding:20px;border-radius:16px;background:#fff5f2;border:1px solid #efc9c2;">
                <p style="margin:0 0 10px;font-size:13px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#9b4338;">
                    Rejection Reason
                </p>
                <p style="margin:0;font-size:15px;line-height:1.7;color:#6f443d;white-space:pre-line;">
                    {{ $rejectionReason }}
                </p>
            </div>

            <p style="margin:0 0 12px;font-size:15px;line-height:1.7;color:#5f4c3d;">
                You may review the reason above, update your documents or details if needed, and submit a new application afterward.
            </p>

            <p style="margin:0;font-size:14px;line-height:1.7;color:#7b6857;">
                If you need help, contact us at {{ $supportEmail }}.
            </p>
        </div>
    </div>
</body>
</html>
