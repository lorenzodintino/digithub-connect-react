@php
    $showCustomer ??= false;

    $service = $booking->service;
    $start = $booking->startsAtIn($timezone);
    $end = $booking->endsAtIn($timezone);

    $rows = [
        __('Service') => $service->name,
        __('When') => ucfirst($start->translatedFormat('l d F Y')).' · '.$start->format('H:i').' – '.$end->format('H:i').' ('.$timezone.')',
        __('With') => $booking->operator->name,
        __('Where') => $service->location_value
            ? $service->location_type->label().' · '.$service->location_value
            : $service->location_type->label(),
    ];

    if ($service->formattedPrice()) {
        $rows[__('Price')] = $service->formattedPrice();
    }

    if ($showCustomer) {
        $contact = $booking->customer_email;

        if ($booking->customer_phone) {
            $contact .= ' · '.$booking->customer_phone;
        }

        $rows[__('Customer')] = $booking->customer_name.' — '.$contact;

        if ($booking->customer_notes) {
            $rows[__('Notes')] = $booking->customer_notes;
        }
    }
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e4e7; border-radius:10px; border-collapse:separate; overflow:hidden;">
    @foreach ($rows as $label => $value)
        <tr>
            <td style="padding:12px 16px; font-size:13px; color:#71717a; white-space:nowrap; vertical-align:top; {{ $loop->first ? '' : 'border-top:1px solid #f4f4f5;' }}">
                {{ $label }}
            </td>
            <td style="padding:12px 16px; font-size:14px; color:#18181b; text-align:right; vertical-align:top; {{ $loop->first ? '' : 'border-top:1px solid #f4f4f5;' }}">
                {{ $value }}
            </td>
        </tr>
    @endforeach
</table>
