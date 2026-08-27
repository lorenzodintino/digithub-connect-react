@extends('mail.layout')

@section('heading', __('A booking was cancelled'))
@section('intro', __('The customer cancelled. The slot is free again.'))

@section('content')
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
