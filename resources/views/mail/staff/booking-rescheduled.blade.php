@extends('mail.layout')

@section('heading', __('A booking has moved'))
@section('intro', __('The customer picked a new time. Your agenda is already up to date.'))

@section('content')
    @if ($booking->original_starts_at)
        <p style="margin:0 0 12px 0; font-size:14px; line-height:1.6; color:#71717a;">
            {{ __('Previously :date', ['date' => $booking->original_starts_at->setTimezone($timezone)->translatedFormat('D d M Y, H:i')]) }}
        </p>
    @endif

    @include('mail.partials.booking-summary', ['showCustomer' => true])

    @include('mail.partials.button', [
        'url' => route('app.bookings', ['q' => $booking->customer_email]),
        'color' => $tenant->brand_color,
        'label' => __('Open your agenda'),
    ])

    <p style="margin:0; font-size:13px; line-height:1.6; color:#71717a;">
        {{ __('You will be asked to sign in first, then taken straight to it.') }}
    </p>
@endsection
