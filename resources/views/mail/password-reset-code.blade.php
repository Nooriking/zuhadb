<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $appName }} — Reset code</title>
</head>
<body style="margin:0;padding:0;background:#f3efe6;font-family:Georgia,'Times New Roman',Times,serif;color:#1a1a1a;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3efe6;padding:40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e4d9c5;border-radius:16px;overflow:hidden;box-shadow:0 12px 40px rgba(26,26,26,0.08);">
                    {{-- Brand header --}}
                    <tr>
                        <td style="background:linear-gradient(160deg,#111111 0%,#2a2418 55%,#1a1a1a 100%);padding:36px 28px 28px;text-align:center;">
                            @if (! empty($logoPath) && is_file($logoPath))
                                <img
                                    src="{{ $message->embed($logoPath) }}"
                                    alt="{{ $appName }}"
                                    width="88"
                                    height="88"
                                    style="display:block;margin:0 auto 16px;width:88px;height:88px;object-fit:contain;border-radius:18px;background:rgba(255,255,255,0.06);padding:8px;border:1px solid rgba(212,175,55,0.35);"
                                >
                            @endif
                            <div style="font-family:Georgia,serif;font-size:22px;font-weight:700;letter-spacing:0.18em;color:#d4af37;text-transform:uppercase;">
                                {{ $appName }}
                            </div>
                            @if (! empty($slogan))
                                <div style="margin-top:8px;font-size:12px;letter-spacing:0.12em;color:#c9b896;text-transform:uppercase;">
                                    {{ $slogan }}
                                </div>
                            @endif
                            <div style="margin:18px auto 0;width:64px;height:2px;background:linear-gradient(90deg,transparent,#d4af37,transparent);"></div>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px 28px 12px;text-align:center;">
                            <div style="font-size:13px;letter-spacing:0.16em;text-transform:uppercase;color:#8a7a5c;margin-bottom:10px;">
                                Password reset
                            </div>
                            <h1 style="margin:0 0 12px;font-size:24px;line-height:1.3;font-weight:700;color:#1a1a1a;">
                                Your secure code
                            </h1>
                            <p style="margin:0 auto 24px;max-width:400px;font-size:15px;line-height:1.6;color:#5c5346;font-family:Arial,Helvetica,sans-serif;">
                                Enter this 6-digit code to set a new password for your {{ $appName }} account.
                            </p>

                            <table role="presentation" cellspacing="0" cellpadding="0" style="margin:0 auto 8px;">
                                <tr>
                                    <td style="background:#111111;border:1px solid #d4af37;border-radius:12px;padding:18px 28px;">
                                        <div style="font-family:'Courier New',Courier,monospace;font-size:36px;font-weight:700;letter-spacing:0.42em;color:#d4af37;line-height:1;">
                                            {{ $code }}
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:20px 0 0;font-size:13px;line-height:1.6;color:#8a7a5c;font-family:Arial,Helvetica,sans-serif;">
                                This code expires in <strong style="color:#1a1a1a;">{{ $expiresMinutes }} minutes</strong>.
                                <br>
                                If you did not request a reset, you can safely ignore this email.
                            </p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding:24px 28px 28px;text-align:center;border-top:1px solid #efe6d6;">
                            <div style="font-size:12px;line-height:1.7;color:#8a7a5c;font-family:Arial,Helvetica,sans-serif;">
                                @if (! empty($phone))
                                    Support: <span style="color:#1a1a1a;">{{ $phone }}</span><br>
                                @endif
                                @if (! empty($address))
                                    {{ $address }}<br>
                                @endif
                                @if (! empty($manufacturer))
                                    {{ $manufacturer }}
                                @endif
                            </div>
                            <div style="margin-top:14px;font-size:11px;letter-spacing:0.08em;color:#b3a68d;font-family:Arial,Helvetica,sans-serif;">
                                © {{ date('Y') }} {{ $appName }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
