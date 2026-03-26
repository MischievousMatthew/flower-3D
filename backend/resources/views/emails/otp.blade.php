<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your verification code</title>
</head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:40px 20px;">
    <tr>
      <td align="center">
        <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);">

          <tr>
            <td style="background:#2d3748;padding:32px 40px;text-align:center;">
              <span style="color:#ffffff;font-size:22px;font-weight:500;">🌸 Bloomcraft</span>
            </td>
          </tr>

          <tr>
            <td style="padding:40px;">
              <p style="margin:0 0 8px;color:#718096;font-size:14px;">Your verification code</p>

              <div style="text-align:center;margin:28px 0;">
                <span style="font-size:40px;font-weight:600;letter-spacing:12px;">
                  {{ $otp }}
                </span>
              </div>

              <p style="color:#4a5568;">
                This code expires in <strong>{{ $expiryMinutes }} minutes</strong>.
              </p>

              <p style="color:#a0aec0;font-size:13px;">
                If you didn't request this, ignore this email.
              </p>
            </td>
          </tr>

          <tr>
            <td style="text-align:center;padding:20px;">
              <p style="color:#cbd5e0;font-size:12px;">
                © {{ date('Y') }} Bloomcraft
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>