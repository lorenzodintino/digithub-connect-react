<?php

namespace App\Http\Requests;

use App\Models\AvailabilityRule;
use App\Support\Week;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class WeeklyScheduleRequest extends FormRequest
{
    /**
     * Who may edit whose hours is decided by the controller, on the member.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'member' => ['required', 'integer'],
            'schedule' => ['required', 'array'],
            'schedule.*.enabled' => ['required', 'boolean'],
            'schedule.*.ranges' => ['array'],
            'schedule.*.ranges.*.starts_at' => ['required', 'date_format:H:i'],
            'schedule.*.ranges.*.ends_at' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * The ranges of the open days, in order, keyed by weekday.
     *
     * Each range has to end after it starts and none may overlap the one
     * before it; the error lands on the range at fault, naming the day.
     *
     * @return array<int, array<int, array{starts_at: string, ends_at: string}>>
     */
    public function schedule(): array
    {
        $schedule = [];

        foreach ($this->validated('schedule') as $weekday => $day) {
            if (! $day['enabled']) {
                continue;
            }

            $sorted = array_values($day['ranges'] ?? []);
            usort($sorted, fn (array $first, array $second): int => AvailabilityRule::toMinutes($first['starts_at']) <=> AvailabilityRule::toMinutes($second['starts_at']));
            $previousEnd = null;

            foreach ($sorted as $index => $range) {
                $start = AvailabilityRule::toMinutes($range['starts_at']);
                $end = AvailabilityRule::toMinutes($range['ends_at']);

                if ($end <= $start) {
                    throw ValidationException::withMessages([
                        "schedule.{$weekday}.ranges.{$index}.ends_at" => __('The end time must be after the start time on :day.', ['day' => Week::label((int) $weekday)]),
                    ]);
                }

                if ($previousEnd !== null && $start < $previousEnd) {
                    throw ValidationException::withMessages([
                        "schedule.{$weekday}.ranges.{$index}.starts_at" => __('Time ranges overlap on :day.', ['day' => Week::label((int) $weekday)]),
                    ]);
                }

                $previousEnd = $end;

                $schedule[(int) $weekday][] = [
                    'starts_at' => $range['starts_at'],
                    'ends_at' => $range['ends_at'],
                ];
            }
        }

        return $schedule;
    }
}
