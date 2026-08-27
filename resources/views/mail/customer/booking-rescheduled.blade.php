@extends('mail.layout')

@section('heading', __('Your booking has moved'))
@section('intro', __('Hi :name, the appointment has a new time. Everything else stays the same.', ['name' => $booking->customer_name]))

@section('content')
    @if ($booking->original_starts_at)
        <p style="margin:0 0 12px 0; font-size:14px; line-height:1.6; color:#71717a;">
            {{ __('Previously :date', ['date' => $booking->original_starts_at->setTimezone($timezone)->translatedFormat('D d M Y, H:i')]) }}
        </p>
    @endif

    @include('mail.partials.booking-summary')

    @include('mail.partials.button', [
        'url' => $manageUrl,
        'color' => $tenant->brand_color,
        'label' => __('Manage your booking'),
    ])
@endsection
