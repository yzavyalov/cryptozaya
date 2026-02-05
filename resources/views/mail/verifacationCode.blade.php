<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verification Code</title>
</head>

<body style="margin:0; padding:0; font-family:Arial, sans-serif; background:#f4f7fb;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
    <tr>
        <td align="center">
            <table width="480" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:8px; padding:40px;">
                <tr>
                    <td align="center">
                        <p style="margin:0 0 20px; font-size:16px; color:#1f3a8a;">
                            Your verification code
                        </p>

                        <div style="font-size:32px; font-weight:bold; letter-spacing:6px; color:#1f3a8a;">
                            {{ $code }}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
