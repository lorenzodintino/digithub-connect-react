<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <title>{{ __('Background jobs failed') }}</title>
</head>
<body style="margin:0; padding:24px; background:#f4f4f5; font-family:system-ui,-apple-system,'Segoe UI',sans-serif; color:#3f3f46;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; margin:0 auto; background:#ffffff; border-radius:12px;">
        <tr>
            <td style="padding:28px;">
                <h1 style="margin:0 0 8px 0; font-size:20px; color:#18181b;">{{ __('Background jobs failed') }}</h1>

                <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
                    {{ __('In the last hour :count jobs failed. Emails that failed did not reach anybody.', ['count' => $failures->count()]) }}
                </p>

                @foreach ($byPortal as $portal => $lines)
                    <p style="margin:16px 0 4px 0; font-size:14px; font-weight:600; color:#18181b;">
                        {{ $portal }} — {{ __(':count failures', ['count' => $lines->count()]) }}
                    </p>

                    <ul style="margin:0; padding-left:18px; font-size:13px; line-height:1.6; color:#52525b;">
                        @foreach ($lines->take(5) as $line)
                            <li>
                                <strong>{{ $line->context['job'] ?? '—' }}</strong>
                                — {{ $line->context['error'] ?? '' }}
                            </li>
                        @endforeach
                    </ul>
                @endforeach

                <p style="margin:20px 0 0 0; font-size:13px; line-height:1.6; color:#71717a;">
                    {{ __('The full list is in the register, and the jobs themselves can be retried from the queue page.') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
