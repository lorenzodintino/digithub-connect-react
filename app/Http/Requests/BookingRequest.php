<?php

namespace App\Http\Requests;

use App\Models\Service;
use App\Models\ServiceQuestion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * The customer, slot and service answers shared by both booking forms.
 */
abstract class BookingRequest extends FormRequest
{
    /**
     * @return array<string, array<int, ValidationRule|In|string>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'slot' => ['required', 'date'],
        ];

        foreach ($this->questions() as $question) {
            $key = 'answers.'.$question->id;
            $presence = $question->is_required ? 'required' : 'nullable';

            $rules[$key] = match (true) {
                $question->type->isMultiple() => [$presence, 'array'],
                $question->type->needsOptions() => [$presence, 'string', Rule::in($question->choices())],
                default => [$presence, 'string', 'max:'.$question->type->maxLength()],
            };

            if ($question->type->isMultiple()) {
                $rules[$key.'.*'] = ['string', Rule::in($question->choices())];
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('name'),
            'email' => __('email'),
            'phone' => __('phone'),
            'notes' => __('notes'),
            'privacy' => __('privacy consent'),
            'slot' => __('time'),
            ...$this->questions()->mapWithKeys(fn (ServiceQuestion $question) => ['answers.'.$question->id => mb_strtolower($question->label)])->all(),
        ];
    }

    /**
     * The answers, keyed by question id, of the questions the service asks.
     *
     * @return array<int, string|array<int, string>>
     */
    public function answers(): array
    {
        $answers = $this->validated('answers', []);

        return $this->questions()->mapWithKeys(fn (ServiceQuestion $question) => [$question->id => $answers[$question->id] ?? ''])->all();
    }

    /**
     * @return Collection<int, ServiceQuestion>
     */
    private function questions(): Collection
    {
        $service = $this->route('service') ?? $this->route('ownService');

        return $service instanceof Service ? $service->bookingConfiguration()->bookingQuestions() : collect();
    }
}
