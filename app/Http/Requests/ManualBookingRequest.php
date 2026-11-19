<?php

namespace App\Http\Requests;

use App\Models\Booking;

class ManualBookingRequest extends BookingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }
}
