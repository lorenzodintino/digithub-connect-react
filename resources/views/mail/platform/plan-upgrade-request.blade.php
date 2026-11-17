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
                <h1 style="margin:0 0 12px 0; font-size:20px; color:#18181b;">{{ __('A manager asks for a bigger plan') }}</h1>

                <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
                    {{ __(':manager (:email) asks to move :portal from the :current plan to :requested.', ['manager' => $manager->name, 'email' => $manager->email, 'portal' => $tenant->name, 'current' => $current?->name ?? __('no plan'), 'requested' => $requested->name]) }}
                </p>

                @if ($reason)
                    <p style="margin:0 0 16px 0; font-size:15px; line-height:1.6;">
                        {{ __('Why now: :reason.', ['reason' => $reason]) }}
                    </p>
                @endif

                @if ($note)
                    <p style="margin:0 0 16px 0; padding:12px 14px; border-radius:8px; background:#f4f4f5; font-size:15px; line-height:1.6; white-space:pre-line;">{{ $note }}</p>
                @endif

                <p style="margin:20px 0 0 0; font-size:13px; line-height:1.6; color:#71717a;">
                    {{ __('Change the plan from the portal page in the administration, or from the License Manager, then let the manager know.') }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
