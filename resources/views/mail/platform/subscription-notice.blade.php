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

                @if ($notice === 'expiring')
                    <h1 style="margin:0 0 12px 0; font-size:20px; color:#18181b;">{{ __('Your subscription is about to end') }}</h1>
                    <p style="margin:0; font-size:15px; line-height:1.6;">
                        {{ __('The period paid for your portal ends on :date. Renew it before then and nothing changes for you or your customers.', ['date' => $lastPaidDay]) }}
                    </p>
                @elseif ($notice === 'grace')
                    <h1 style="margin:0 0 12px 0; font-size:20px; color:#18181b;">{{ __('Your subscription has ended') }}</h1>
                    <p style="margin:0; font-size:15px; line-height:1.6;">
                        {{ __('The period paid for your portal ended on :date. On :suspension the portal will be suspended and your customers will no longer be able to book.', ['date' => $lastPaidDay, 'suspension' => $suspensionDay]) }}
                    </p>
                @else
                    <h1 style="margin:0 0 12px 0; font-size:20px; color:#18181b;">{{ __('Your portal has been suspended') }}</h1>
                    <p style="margin:0; font-size:15px; line-height:1.6;">
                        {{ __('The period paid for your portal ended on :date and has not been renewed. The booking page is offline and the accounts of the portal cannot sign in. Your data is kept: once the payment is recorded, everything is back as it was.', ['date' => $lastPaidDay]) }}
                    </p>
                @endif

                <p style="margin:20px 0 0 0; font-size:13px; line-height:1.6; color:#71717a;">
                    {{ __('To renew, contact the platform administrator.') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
