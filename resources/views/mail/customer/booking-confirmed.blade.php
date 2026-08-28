@extends('mail.layout')

@section('heading', __('Your booking is confirmed'))
@section('intro', __('Hi :name, here are the details. Nothing else is needed from you.', ['name' => $booking->customer_name]))

@section('content')
    @include('mail.partials.booking-summary')

    @include('mail.partials.button', [
        'url' => $manageUrl,
        'color' => $tenant->brand_color,
        'label' => __('Manage your booking'),
    ])

    <p style="margin:0; font-size:13px; line-height:1.6; color:#71717a;">
        {{ __('Use that link to move or cancel your booking. Keep this email: the link is the only way back to it.') }}

        @if ($booking->customerChangeDeadline())
            {{ __('You can do it yourself until :date.', [
                'date' => $booking->customerChangeDeadline()->setTimezone($booking->customer_timezone)->translatedFormat('D d M Y, H:i'),
            ]) }}
        @endif
    </p>
@endsection
