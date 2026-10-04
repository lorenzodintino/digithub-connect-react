{{-- The extra questions of the service, rendered from what the portal asked for. --}}
@foreach ($this->questions as $question)
    <div wire:key="question-{{ $question->id }}">
        @switch ($question->type)
            @case (\App\Enums\QuestionType::LongText)
                <flux:textarea
                    wire:model="answers.{{ $question->id }}"
                    :label="$question->label"
                    :required="$question->is_required"
                    rows="3"
                />
                @break

            @case (\App\Enums\QuestionType::SingleChoice)
                <flux:select wire:model="answers.{{ $question->id }}" :label="$question->label" :required="$question->is_required">
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach ($question->choices() as $choice)
                        <flux:select.option :value="$choice">{{ $choice }}</flux:select.option>
                    @endforeach
                </flux:select>
                @break

            @case (\App\Enums\QuestionType::MultipleChoice)
                <flux:checkbox.group wire:model="answers.{{ $question->id }}" :label="$question->label">
                    @foreach ($question->choices() as $choice)
                        <flux:checkbox :value="$choice" :label="$choice" />
                    @endforeach
                </flux:checkbox.group>
                @break

            @default
                <flux:input
                    wire:model="answers.{{ $question->id }}"
                    :label="$question->label"
                    :required="$question->is_required"
                />
        @endswitch

    </div>
@endforeach
