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

                <h1 style="margin:0 0 12px 0; font-size:20px; color:#18181b;">
                    {{ __('Your request for the :plan plan', ['plan' => $request->requested_plan]) }}
                </h1>

                <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
                    @switch($request->status->value)
                        @case('in_progress')
                            {{ __('We have taken your request in charge and we are working on it.') }}
                            @break
                        @case('handled')
                            {{ __('Done: your portal is now on the :plan plan or above.', ['plan' => $request->requested_plan]) }}
                            @break
                        @case('rejected')
                            {{ __('We could not accept your request this time.') }}
                            @break
                        @default
                            {{ __('The state of your request changed: :status.', ['status' => mb_strtolower($request->status->label())]) }}
                    @endswitch
                </p>

                @if ($request->response)
                    <p style="margin:0 0 16px 0; padding:12px 14px; border-radius:8px; background:#f4f4f5; font-size:15px; line-height:1.6; white-space:pre-line;">{{ $request->response }}</p>
                @endif

                <p style="margin:20px 0 0 0; font-size:13px; line-height:1.6; color:#71717a;">
                    {{ __('You can follow your request in Portal settings, under Your plan.') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
