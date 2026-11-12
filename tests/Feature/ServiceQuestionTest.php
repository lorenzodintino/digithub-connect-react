<?php

use App\Enums\QuestionType;
use App\Models\Booking;
use App\Models\Service;
use App\Models\ServiceQuestion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SlotFinder;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Europe/Rome']);
    $this->manager = User::factory()->manager($this->tenant)->create();
    $this->operator = User::factory()->operator($this->tenant)->create();

    $this->service = Service::factory()->for($this->tenant)->create([
        'duration_minutes' => 30,
        'slot_interval_minutes' => 30,
        'buffer_before_minutes' => 0,
        'buffer_after_minutes' => 0,
        'min_notice_minutes' => 0,
        'cancellation_notice_hours' => 0,
    ]);

    $this->service->operators()->attach($this->operator);

    foreach (range(0, 6) as $weekday) {
        $this->operator->availabilityRules()->create([
            'weekday' => $weekday,
            'starts_at' => '09:00:00',
            'ends_at' => '12:00:00',
        ]);
    }
});

/**
 * Add a question to the test service.
 */
function askQuestion(array $attributes = []): ServiceQuestion
{
    return test()->service->questions()->create([
        'label' => 'Prima visita?',
        'type' => QuestionType::SingleChoice,
        'options' => ['Sì', 'No'],
        'is_required' => true,
        'position' => 0,
        ...$attributes,
    ]);
}

/**
 * Book the first free time of tomorrow from the public page, with these answers.
 *
 * @param  array<int, string|array<int, string>>  $answers
 */
function bookWith(array $answers = []): TestResponse
{
    $tomorrow = CarbonImmutable::now(test()->tenant->timezone)->addDay()->startOfDay();
    $slot = (new SlotFinder(test()->service, test()->operator))->slotsForDay($tomorrow)[0];

    return test()->post(route('portal.service.book', [test()->tenant, test()->service]), bookingForm($slot, [
        'name' => 'Anna Ferrari',
        'email' => 'anna@example.test',
        'answers' => $answers,
    ]));
}

/**
 * A question as a new row of the service form.
 *
 * @return array{id: null, label: string, type: string, options: string, is_required: bool}
 */
function newQuestion(string $label, QuestionType $type = QuestionType::ShortText, string $options = '', bool $required = false): array
{
    return ['id' => null, 'label' => $label, 'type' => $type->value, 'options' => $options, 'is_required' => $required];
}

test('a manager adds questions of every kind to a service', function () {
    $this->actingAs($this->manager)
        ->put(route('app.services.update', $this->service->slug), serviceForm($this->service, [
            'questions' => [
                newQuestion('Prima visita?', QuestionType::SingleChoice, "Sì\nNo", true),
                newQuestion('Come ci hai conosciuto?', QuestionType::LongText),
            ],
        ]))
        ->assertSessionHasNoErrors();

    $questions = $this->service->questions()->get();

    expect($questions)->toHaveCount(2)
        ->and($questions[0]->type)->toBe(QuestionType::SingleChoice)
        ->and($questions[0]->choices())->toBe(['Sì', 'No'])
        ->and($questions[0]->is_required)->toBeTrue()
        ->and($questions[1]->type)->toBe(QuestionType::LongText)
        ->and($questions[1]->options)->toBeNull()
        ->and($questions[1]->position)->toBe(1);
});

test('a question without a label is refused', function () {
    $this->actingAs($this->manager)
        ->put(route('app.services.update', $this->service->slug), serviceForm($this->service, ['questions' => [newQuestion('')]]))
        ->assertSessionHasErrors('questions.0.label');

    expect($this->service->questions()->count())->toBe(0);
});

test('a service asks at most ten questions', function () {
    $questions = array_map(fn (int $index) => newQuestion('Domanda '.$index), range(1, ServiceQuestion::LIMIT + 1));

    $this->actingAs($this->manager)
        ->put(route('app.services.update', $this->service->slug), serviceForm($this->service, ['questions' => $questions]))
        ->assertSessionHasErrors('questions');

    expect($this->service->questions()->count())->toBe(0);
});

test('rewording a question keeps the answers already given linked to it', function () {
    $question = askQuestion();
    bookWith([$question->id => 'Sì'])->assertSessionHasNoErrors();

    $form = serviceForm($this->service);
    $form['questions'][0]['label'] = 'È la prima visita?';

    $this->actingAs($this->manager)
        ->put(route('app.services.update', $this->service->slug), $form)
        ->assertSessionHasNoErrors();

    expect($this->service->questions()->sole()->id)->toBe($question->id)
        ->and(Booking::query()->sole()->answers->sole()->service_question_id)->toBe($question->id);
});

test('a required question stops the booking until it is answered', function () {
    $question = askQuestion();

    bookWith()->assertSessionHasErrors('answers.'.$question->id);

    expect(Booking::query()->count())->toBe(0);
});

test('an answer outside the offered choices is refused', function () {
    $question = askQuestion();

    bookWith([$question->id => 'Forse'])->assertSessionHasErrors('answers.'.$question->id);
});

test('a missing required answer shows one error for each question type', function (QuestionType $type) {
    $question = askQuestion(['label' => 'Required response', 'type' => $type]);

    bookWith()->assertSessionHasErrors(['answers.'.$question->id => 'The required response field is required.']);

    expect(session('errors')->get('answers.'.$question->id))->toHaveCount(1);
    expect(Booking::query()->count())->toBe(0);
})->with(QuestionType::cases());

test('the answers reach the booking with the wording of the day', function () {
    $single = askQuestion();
    $free = askQuestion(['label' => 'Note per lo studio', 'type' => QuestionType::ShortText, 'options' => null, 'is_required' => false, 'position' => 1]);

    bookWith([$single->id => 'Sì', $free->id => 'Arrivo con i documenti'])->assertSessionHasNoErrors();

    $booking = Booking::query()->sole();

    expect($booking->answers)->toHaveCount(2)
        ->and($booking->answers[0]->label)->toBe('Prima visita?')
        ->and($booking->answers[0]->value)->toBe('Sì')
        ->and($booking->answers[1]->value)->toBe('Arrivo con i documenti');
});

test('a multiple choice answer is stored as one readable line', function () {
    $question = askQuestion([
        'label' => 'Di cosa parliamo?',
        'type' => QuestionType::MultipleChoice,
        'options' => ['Tasse', 'Contratti', 'Altro'],
        'is_required' => false,
    ]);

    bookWith([$question->id => ['Tasse', 'Altro']])->assertSessionHasNoErrors();

    expect(Booking::query()->sole()->answers->first()->value)->toBe('Tasse, Altro');
});

test('an unanswered optional question leaves no empty row behind', function () {
    askQuestion(['is_required' => false]);

    bookWith()->assertSessionHasNoErrors();

    expect(Booking::query()->sole()->answers)->toHaveCount(0);
});

test('deleting a question does not rewrite the answers already given', function () {
    $question = askQuestion();

    bookWith([$question->id => 'Sì'])->assertSessionHasNoErrors();

    $this->actingAs($this->manager)
        ->put(route('app.services.update', $this->service->slug), serviceForm($this->service, ['questions' => []]))
        ->assertSessionHasNoErrors();

    $answer = Booking::query()->sole()->answers->sole();

    expect($this->service->questions()->count())->toBe(0)
        ->and($answer->label)->toBe('Prima visita?')
        ->and($answer->value)->toBe('Sì')
        ->and($answer->service_question_id)->toBeNull();
});

test('the agenda shows the answers on the booking card', function () {
    $question = askQuestion();

    bookWith([$question->id => 'Sì'])->assertSessionHasNoErrors();

    $booking = Booking::query()->sole();

    $this->actingAs($this->manager)
        ->get(route('app.bookings', ['booking' => $booking->uid]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('details.answers.0.label', 'Prima visita?')
            ->where('details.answers.0.value', 'Sì'));
});

test('erasing a customer takes their answers with it', function () {
    $question = askQuestion();

    bookWith([$question->id => 'Sì'])->assertSessionHasNoErrors();

    $booking = Booking::query()->sole();
    $booking->update(['starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addMinutes(30)]);

    $this->actingAs($this->manager)
        ->post(route('app.bookings.anonymise', $booking))
        ->assertSessionHasNoErrors();

    expect($booking->fresh()->answers)->toHaveCount(0);
});
