@extends('mail.layout')

@section('heading', __('We received your request'))
@section('intro', __('Hi :name, we are holding this time for you. You will get another email as soon as it is confirmed.', ['name' => $booking->customer_name]))

@section('content')
    @include('mail.partials.booking-summary')

    @include('mail.partials.button', [
        'url' => $manageUrl,
        'color' => $tenant->brand_color,
        'label' => __('See your request'),
    ])

    <p style="margin:0; font-size:13px; line-height:1.6; color:#71717a;">
        {{ __('You can withdraw the request from that link at any time.') }}
    </p>
@endsection
