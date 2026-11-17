<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin:0; padding:24px; background:#f4f4f5; font-family:system-ui,-apple-system,'Segoe UI',sans-serif; color:#3f3f46;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:12px;">
        <tr>
            <td style="padding:28px;">
                <p style="margin:0 0 4px 0; font-size:13px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; color:#71717a;">
                    {{ config('app.name') }}
                </p>
                <h1 style="margin:0 0 12px 0; font-size:20px; color:#18181b;">{{ __('The License Manager answers again') }}</h1>
                <p style="margin:0; font-size:15px; line-height:1.6;">
                    {{ __('The connection works again and the licenses are being updated.') }}
                    @if ($since)
                        {{ __('It had been down since :since.', ['since' => $since->setTimezone(config('app.timezone'))->translatedFormat('d M Y, H:i')]) }}
                    @endif
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
