<?php

namespace App\Http\Requests;

class PublicBookingRequest extends BookingRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'privacy' => ['accepted'],
            'operator' => ['nullable', 'integer'],
            'tz' => ['nullable', 'string'],
        ];
    }
}
