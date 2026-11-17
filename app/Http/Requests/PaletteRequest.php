<?php

namespace App\Http\Requests;

use App\Enums\Palette;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaletteRequest extends FormRequest
{
    /**
     * Who may change it is decided by the TenantPolicy in the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'palette' => ['required', Rule::enum(Palette::class)],
        ];
    }
}
