@extends('mail.layout')

@section('heading', __('Your email settings work'))
@section('intro', __('If you are reading this, bookings for :name will reach your customers.', ['name' => $tenant->name]))

@section('content')
    <p style="margin:0; font-size:14px; line-height:1.6; color:#52525b;">
        {{ __('This message was sent through the SMTP server configured for this portal.') }}
    </p>
@endsection
