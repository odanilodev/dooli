<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $mailTitle }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:28px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e5eaf1;">
                    <tr>
                        <td style="padding:26px 30px 12px;">
                            <h1 style="margin:0;font-size:21px;line-height:1.3;color:#111827;">{{ $mailTitle }}</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:0 30px 8px;font-size:15px;line-height:1.65;color:#374151;">
                            {!! nl2br(e($mailBody)) !!}
                        </td>
                    </tr>
                    @if ($code)
                        <tr>
                            <td style="padding:8px 30px 18px;">
                                <div style="display:inline-block;padding:14px 20px;border-radius:10px;background:#eef8f4;border:1px solid #bde8d7;font-size:26px;font-weight:700;letter-spacing:6px;color:#0f766e;">
                                    {{ $code }}
                                </div>
                            </td>
                        </tr>
                    @endif
                    @if ($actionUrl)
                        <tr>
                            <td style="padding:10px 30px 26px;">
                                <a href="{{ $actionUrl }}" style="display:inline-block;padding:12px 20px;border-radius:9px;background:#17c993;color:#06281f;font-size:14px;font-weight:700;text-decoration:none;">
                                    {{ $actionLabel ?: 'Abrir' }}
                                </a>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:18px 30px 24px;border-top:1px solid #eef1f5;font-size:12px;line-height:1.5;color:#8a94a6;">
                            {{ $companyName ? 'Mensagem enviada por '.$companyName.'.' : 'Mensagem automática.' }}
                            Se você não reconhece esta mensagem, ignore este e-mail.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
