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

          <!-- Header -->
          <tr>
            <td style="background:#2d3748;padding:32px 40px;text-align:center;">
              <span style="color:#ffffff;font-size:22px;font-weight:500;letter-spacing:-0.5px;">🌸 Bloomcraft</span>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:40px;">
              <p style="margin:0 0 8px;color:#718096;font-size:14px;">Your verification code</p>
              <div style="text-align:center;margin:28px 0;">
                <span style="display:inline-block;font-size:40px;font-weight:600;letter-spacing:12px;color:#2d3748;background:#f7fafc;padding:20px 32px;border-radius:10px;border:1px solid #e2e8f0;">{{ $otp }}</span>
              </div>
              <p style="margin:0 0 16px;color:#4a5568;font-size:15px;line-height:1.6;">Enter this code to complete your verification. It expires in <strong>{{ $expiryMinutes }} minutes</strong> and can only be used once.</p>
              <p style="margin:0;color:#a0aec0;font-size:13px;">If you didn't request this, you can safely ignore this email. Your account is secure.</p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:20px 40px;border-top:1px solid #f0f0f0;text-align:center;">
              <p style="margin:0;color:#cbd5e0;font-size:12px;">© {{ date('Y') }} Bloomcraft. All rights reserved.</p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>