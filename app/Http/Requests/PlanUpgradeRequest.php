<?php

namespace App\Http\Requests;

use App\Actions\Plans\RequestPlanUpgrade;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PlanUpgradeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->isManager() && $user->tenant !== null;
    }

    /**
     * Only a plan above the current one can be asked for.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'plan_id' => ['required', 'integer', Rule::in($user->tenant->upgradePlans()->pluck('id')->all())],
            'reason' => ['nullable', 'string', Rule::in(array_keys(RequestPlanUpgrade::reasons()))],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * A portal has one request open at a time: the next one waits for the answer to this.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $user */
                $user = $this->user();

                if ($user->tenant->planRequests()->open()->exists()) {
                    $validator->errors()->add('plan_id', __('You already have a request waiting for an answer.'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['plan_id.in' => __('Pick a plan above the one you have.')];
    }

    public function plan(): Plan
    {
        return Plan::query()->findOrFail($this->validated('plan_id'));
    }
}
