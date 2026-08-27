@php
    $color ??= '#18181b';
@endphp

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:20px 0;">
    <tr>
        <td style="border-radius:8px; background-color:{{ $color }};">
            <a
                href="{{ $url }}"
                style="display:inline-block; padding:11px 20px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:8px;"
            >{{ $label }}</a>
        </td>
    </tr>
</table>
