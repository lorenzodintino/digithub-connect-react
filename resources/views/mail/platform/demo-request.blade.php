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
                <h1 style="margin:0 0 12px 0; font-size:20px; color:#18181b;">{{ __('New demo request') }}</h1>

                <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
                    {{ __('Someone asked for a demo from the home page. Reply to this email to answer them.') }}
                </p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px; line-height:1.6;">
                    <tr>
                        <td style="padding:6px 12px 6px 0; width:140px; color:#71717a; vertical-align:top;">{{ __('Name') }}</td>
                        <td style="padding:6px 0; color:#18181b;">{{ $requesterName }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 12px 6px 0; color:#71717a; vertical-align:top;">{{ __('Email') }}</td>
                        <td style="padding:6px 0; color:#18181b;">{{ $requesterEmail }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 12px 6px 0; color:#71717a; vertical-align:top;">{{ __('Business') }}</td>
                        <td style="padding:6px 0; color:#18181b;">{{ $requesterBusiness ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 12px 6px 0; color:#71717a; vertical-align:top;">{{ __('Plan of interest') }}</td>
                        <td style="padding:6px 0; color:#18181b;">{{ $requestedPlan ?? __('Not sure yet') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 12px 6px 0; color:#71717a; vertical-align:top;">{{ __('Page language') }}</td>
                        <td style="padding:6px 0; color:#18181b;">{{ strtoupper($pageLanguage) }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 12px 6px 0; color:#71717a; vertical-align:top;">{{ __('Privacy consent') }}</td>
                        <td style="padding:6px 0; color:#18181b;">{{ __('Given on :date', ['date' => $consentGivenAt]) }}</td>
                    </tr>
                </table>

                @if ($requesterMessage)
                    <p style="margin:20px 0 6px 0; font-size:13px; font-weight:600; color:#71717a;">{{ __('Message') }}</p>
                    <p style="margin:0; padding:12px 14px; border-radius:8px; background:#f4f4f5; font-size:15px; line-height:1.6; white-space:pre-line;">{{ $requesterMessage }}</p>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
