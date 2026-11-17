<?php

namespace App\Http\Requests;

use App\Models\Resource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResourceBlockRequest extends FormRequest
{
    /**
     * Only the manager of the resource's portal takes it out of use.
     */
    public function authorize(): bool
    {
        $resource = $this->route('resource');

        return $resource instanceof Resource && ($this->user()?->can('block', $resource) ?? false);
    }

    /**
     * Wall-clock times of the portal, as `<input type="datetime-local">` sends them.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['starts_at' => __('Start'), 'ends_at' => __('End'), 'reason' => __('Reason')];
    }
}
