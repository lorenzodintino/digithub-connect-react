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

                @if ($configuration)
                    <h1 style="margin:0 0 12px 0; font-size:20px; color:#18181b;">{{ __('The License Manager cannot be used with the current configuration') }}</h1>
                    <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
                        {{ __('The platform asked the License Manager and got the error «:code». Check the license settings (address and key) in the .env file.', ['code' => $code]) }}
                    </p>
                @else
                    <h1 style="margin:0 0 12px 0; font-size:20px; color:#18181b;">{{ __('The License Manager is not answering') }}</h1>
                    <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
                        @if ($since)
                            {{ __('The platform cannot reach the License Manager (error «:code») since :since.', ['code' => $code, 'since' => $since->setTimezone(config('app.timezone'))->translatedFormat('d M Y, H:i')]) }}
                        @else
                            {{ __('The platform cannot reach the License Manager (error «:code»).', ['code' => $code]) }}
                        @endif
                    </p>
                @endif

                <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
                    {{ __('Meanwhile every portal keeps working on the last answer it received, so nobody is blocked and nobody gets a free license: fix the connection as soon as you can. You will get this message again every six hours while the problem lasts, and one when it is solved.') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
