@extends('mail.layout')

@section('heading', __('Your booking has been cancelled'))
@section('intro', __('Hi :name, we are sorry: this appointment will not take place.', ['name' => $booking->customer_name]))

@section('content')
    @include('mail.partials.booking-summary')

    @if ($booking->cancellation_reason)
        <p style="margin:16px 0 0 0; padding:12px 16px; background-color:#fafafa; border-radius:8px; font-size:14px; line-height:1.6; color:#3f3f46;">
            {{ $booking->cancellation_reason }}
        </p>
    @endif

    @include('mail.partials.button', [
        'url' => route('portal.show', $tenant),
        'color' => $tenant->brand_color,
        'label' => __('Book another time'),
    ])
@endsection
