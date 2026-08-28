<?php

namespace App\Enums;

enum QuestionType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';

    /**
     * Human readable label for the question type.
     */
    public function label(): string
    {
        return match ($this) {
            self::ShortText => __('Short answer'),
            self::LongText => __('Long answer'),
            self::SingleChoice => __('Single choice'),
            self::MultipleChoice => __('Multiple choice'),
        };
    }

    /**
     * Determine whether the type is answered by picking from a list.
     */
    public function needsOptions(): bool
    {
        return in_array($this, [self::SingleChoice, self::MultipleChoice], true);
    }

    /**
     * Determine whether the answer is a list rather than one value.
     */
    public function isMultiple(): bool
    {
        return $this === self::MultipleChoice;
    }

    /**
     * The longest answer accepted for this type.
     */
    public function maxLength(): int
    {
        return $this === self::LongText ? 2000 : 255;
    }
}
