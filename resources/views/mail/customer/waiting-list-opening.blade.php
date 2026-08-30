@extends('mail.layout')

@section('heading', __('A place opened up'))
@section('intro', __('Hi :name, a time you were waiting for is free again.', ['name' => $entry->customer_name]))

@section('content')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px; line-height:1.6; color:#3f3f46;">
        <tr>
            <td style="padding:4px 0; color:#71717a;">{{ __('Service') }}</td>
            <td style="padding:4px 0; text-align:right; font-weight:600;">{{ $service->name }}</td>
        </tr>
        <tr>
            <td style="padding:4px 0; color:#71717a;">{{ __('When') }}</td>
            <td style="padding:4px 0; text-align:right; font-weight:600;">
                {{ $slot->translatedFormat('D d M Y, H:i') }}
            </td>
        </tr>
    </table>

    @include('mail.partials.button', [
        'url' => $bookUrl,
        'color' => $tenant->brand_color,
        'label' => __('Book this time'),
    ])

    <p style="margin:0 0 12px 0; font-size:13px; line-height:1.6; color:#71717a;">
        {{ __('The place is not held for you: whoever books first takes it.') }}
    </p>

    <p style="margin:0; font-size:13px; line-height:1.6; color:#a1a1aa;">
        <a href="{{ $leaveUrl }}" style="color:#a1a1aa;">{{ __('Take me off this waiting list') }}</a>
    </p>
@endsection
