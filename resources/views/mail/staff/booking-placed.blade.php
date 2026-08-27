@extends('mail.layout')

@section('heading', __('New booking in your agenda'))
@section('intro', $booking->awaitsConfirmation()
    ? __('It is waiting for your approval before it becomes final.')
    : __('It is already confirmed. Nothing to do unless something changes.'))

@section('content')
    @include('mail.partials.booking-summary', ['showCustomer' => true])

    @include('mail.partials.button', [
        'url' => route('app.bookings', ['q' => $booking->customer_email]),
        'color' => $tenant->brand_color,
        'label' => $booking->awaitsConfirmation() ? __('Review the request') : __('Open your agenda'),
    ])

    <p style="margin:0; font-size:13px; line-height:1.6; color:#71717a;">
        {{ __('You will be asked to sign in first, then taken straight to it.') }}
        {{ __('Times are shown in your own timezone (:timezone).', ['timezone' => $timezone]) }}
    </p>
@endsection
