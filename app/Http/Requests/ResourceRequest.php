<?php

namespace App\Http\Requests;

use App\Enums\ResourceType;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Unique;

class ResourceRequest extends FormRequest
{
    /**
     * A new resource for the manager's portal, or one of its own.
     */
    public function authorize(): bool
    {
        $resource = $this->route('resource');

        return $resource instanceof Resource
            ? $this->user()?->can('update', $resource) ?? false
            : $this->user()?->can('create', Resource::class) ?? false;
    }

    /**
     * Names are unique inside a portal; two portals may both have "Cabina 1".
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string|Unique|Enum>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique(Resource::class, 'name')->where('tenant_id', $user->tenant_id)->ignore($this->route('resource'))],
            'type' => ['required', Rule::enum(ResourceType::class)],
            'description' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'preparation_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'cleanup_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Resource name'),
            'type' => __('Resource type'),
            'preparation_minutes' => __('Preparation (minutes)'),
            'cleanup_minutes' => __('Cleanup (minutes)'),
        ];
    }
}
