<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <title>{{ $tenant->name }}</title>
</head>
<body style="margin:0; padding:24px; background:#f4f4f5; font-family:system-ui,-apple-system,'Segoe UI',sans-serif; color:#3f3f46;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:12px;">
        <tr>
            <td style="padding:28px;">
                <p style="margin:0 0 4px 0; font-size:13px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; color:#71717a;">
                    {{ $tenant->name }}
                </p>
                <h1 style="margin:0 0 12px 0; font-size:20px; color:#18181b;">{{ __('A portal reached the bookings of its plan') }}</h1>

                <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
                    {{ __(':portal has taken :count bookings this month and reached the :limit of the :plan plan. Its customers can still book.', ['portal' => $tenant->name, 'count' => $bookings, 'limit' => $limit, 'plan' => $plan?->name ?? __('no plan')]) }}
                </p>

                <p style="margin:20px 0 0 0; font-size:13px; line-height:1.6; color:#71717a;">
                    {{ __('It is a good moment to talk to the manager about a bigger plan. You will not get another message about this portal until next month.') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
