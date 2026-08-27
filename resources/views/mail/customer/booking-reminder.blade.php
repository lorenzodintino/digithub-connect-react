@extends('mail.layout')

@section('heading', __('See you soon'))
@section('intro', __('Hi :name, a quick reminder about your appointment.', ['name' => $booking->customer_name]))

@section('content')
    @include('mail.partials.booking-summary')

    @include('mail.partials.button', [
        'url' => $manageUrl,
        'color' => $tenant->brand_color,
        'label' => __('Manage your booking'),
    ])

    <p style="margin:0; font-size:13px; line-height:1.6; color:#71717a;">
        {{ __('If you cannot make it, cancelling from that link frees the slot for someone else.') }}
    </p>
@endsection
