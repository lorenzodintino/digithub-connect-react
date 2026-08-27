{{--
    Shared shell for every booking email.

    Styles are inline on purpose: a number of mail clients drop a <style> block,
    and Gmail strips classes entirely.

    Children provide `heading`, optionally `intro`, and `content`. `$tenant` is
    passed in by the mailable.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('heading')</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif; color:#18181b;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0;">@yield('heading')</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px; background-color:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="height:6px; background-color:{{ $tenant->brand_color }}; font-size:0; line-height:0;">&nbsp;</td>
                    </tr>

                    <tr>
                        <td style="padding:28px 28px 8px 28px;">
                            <p style="margin:0 0 4px 0; font-size:13px; font-weight:600; letter-spacing:.04em; text-transform:uppercase; color:{{ $tenant->brand_color }};">
                                {{ $tenant->name }}
                            </p>

                            <h1 style="margin:0; font-size:22px; line-height:1.3; font-weight:600; color:#18181b;">
                                @yield('heading')
                            </h1>

                            @hasSection('intro')
                                <p style="margin:12px 0 0 0; font-size:15px; line-height:1.6; color:#52525b;">
                                    @yield('intro')
                                </p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:16px 28px 28px 28px;">
                            @yield('content')
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
                    <tr>
                        <td style="padding:16px 28px; text-align:center; font-size:12px; line-height:1.6; color:#a1a1aa;">
                            {{ $tenant->name }}
                            @if ($tenant->contact_phone)
                                &middot; {{ $tenant->contact_phone }}
                            @endif
                            @if ($tenant->address)
                                <br>{{ $tenant->address }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
